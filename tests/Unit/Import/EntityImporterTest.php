<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Import;

use Doctrine\DBAL\Driver\PDO\Exception as PDOException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Generator;
use InvalidArgumentException;
use JG\BatchEntityImportBundle\Event\AfterImportEvent;
use JG\BatchEntityImportBundle\Event\BeforeImportEvent;
use JG\BatchEntityImportBundle\Event\RecordImportedSuccessfullyEvent;
use JG\BatchEntityImportBundle\Event\RecordPreparedEvent;
use JG\BatchEntityImportBundle\Event\RecordValueEvent;
use JG\BatchEntityImportBundle\Exception\DatabaseException;
use JG\BatchEntityImportBundle\Import\EntityImporter;
use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EntityImporterTest extends TestCase
{
    /**
     * @param array<string, mixed> $identifier
     */
    #[DataProvider('identifierProvider')]
    public function testIdentifierOfEntityInEvent(array $identifier, string|int $expectedId): void
    {
        $events = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(RecordImportedSuccessfullyEvent::class, static function (RecordImportedSuccessfullyEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $this->createImporter($this->createEntityManager($identifier), $eventDispatcher)->import($this->createMatrix(), new BaseConfiguration());

        self::assertEquals([new RecordImportedSuccessfullyEvent(TestEntity::class, $expectedId)], $events);
        self::assertSame($expectedId, $events[0]->id);
    }

    public static function identifierProvider(): Generator
    {
        yield 'integer' => [['id' => 5], 5];
        yield 'string' => [['id' => 'abc'], 'abc'];
        yield 'numeric string' => [['id' => '5'], '5'];
        yield 'stringable object, e.g. UUID' => [['id' => new class implements Stringable {
            public function __toString(): string
            {
                return '0193a0e2-7b1c-7c3e-9f4a-1d2e3f4a5b6c';
            }
        }], '0193a0e2-7b1c-7c3e-9f4a-1d2e3f4a5b6c'];
        yield 'composite identifier, first value is used' => [['first' => 1, 'second' => 'b'], 1];
        yield 'not supported identifier' => [['id' => new stdClass()], ''];
        yield 'no identifier' => [[], ''];
    }

    public function testExceptionOfListenerIsNotReportedAsDatabaseError(): void
    {
        $exception = new ConnectionLost(new PDOException('lost'), null);
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(RecordImportedSuccessfullyEvent::class, static function () use ($exception): never {
            throw $exception;
        });

        $entityManager = $this->createEntityManager(['id' => 1]);
        $entityManager->expects($this->once())->method('flush');

        // data is already saved, so original exception of listener is thrown, not DatabaseException
        $this->expectExceptionObject($exception);
        $this->createImporter($entityManager, $eventDispatcher)->import($this->createMatrix(), new BaseConfiguration());
    }

    public function testErrorOfDatabaseDuringFlush(): void
    {
        $entityManager = $this->createEntityManager(['id' => 1]);
        $entityManager->method('flush')->willThrowException(new ConnectionLost(new PDOException('lost'), null));

        $this->expectException(DatabaseException::class);
        $this->createImporter($entityManager, new EventDispatcher())->import($this->createMatrix(), new BaseConfiguration());
    }

    public function testNewEntityIsCreatedByConfiguration(): void
    {
        $entity = new TestEntity();
        $entityManager = $this->createEntityManager(['id' => 1]);
        $entityManager->expects($this->once())->method('persist')->with($this->identicalTo($entity));

        $this->createImporter($entityManager, new EventDispatcher())->import($this->createMatrix(), $this->createConfiguration($entity));

        self::assertSame('value', $entity->getTestPrivateProperty());
    }

    #[DataProvider('notManagedClassProvider')]
    public function testNotManagedEntityException(string $class): void
    {
        $configuration = new class($class) extends AbstractImportConfiguration {
            public function __construct(private readonly string $class)
            {
            }

            public function getEntityClassName(): string
            {
                /* @var class-string */
                return $this->class;
            }
        };

        $this->expectExceptionObject(new InvalidArgumentException(\sprintf('Class "%s" is not a managed Doctrine entity.', $class)));
        $this->createImporter($this->createEntityManager([]), new EventDispatcher())->import(new Matrix(), $configuration);
    }

    public static function notManagedClassProvider(): Generator
    {
        yield 'class without manager' => [self::class];
        yield 'not existing class' => ['App\Entity\NotExisting'];
    }

    public function testEventsAreDispatchedInOrder(): void
    {
        $configuration = new BaseConfiguration();
        $matrix = new Matrix(['test_private_property', 'test_public_property'], [
            ['test_private_property' => 'a', 'test_public_property' => 'b'],
            ['test_private_property' => 'c', 'test_public_property' => 'd'],
        ]);

        $events = [];
        $eventDispatcher = new EventDispatcher();
        foreach ([BeforeImportEvent::class, RecordValueEvent::class, RecordPreparedEvent::class, RecordImportedSuccessfullyEvent::class, AfterImportEvent::class] as $eventClass) {
            $eventDispatcher->addListener($eventClass, static function (object $event) use (&$events): void {
                $events[] = $event;
            });
        }

        $this->createImporter($this->createEntityManager(['id' => 1]), $eventDispatcher)->import($matrix, $configuration);

        self::assertSame([
            BeforeImportEvent::class,
            RecordValueEvent::class,
            RecordValueEvent::class,
            RecordPreparedEvent::class,
            RecordValueEvent::class,
            RecordValueEvent::class,
            RecordPreparedEvent::class,
            RecordImportedSuccessfullyEvent::class,
            RecordImportedSuccessfullyEvent::class,
            AfterImportEvent::class,
        ], array_map(static fn (object $event): string => $event::class, $events));

        self::assertInstanceOf(BeforeImportEvent::class, $events[0]);
        self::assertSame($configuration, $events[0]->configuration);
        self::assertSame($matrix, $events[0]->matrix);

        self::assertInstanceOf(RecordValueEvent::class, $events[4]);
        self::assertSame($configuration, $events[4]->configuration);
        self::assertSame($matrix->getRecords()[1], $events[4]->record);
        self::assertSame('test_private_property', $events[4]->columnName);
        self::assertSame(2, $events[4]->rowNumber);
        self::assertSame('c', $events[4]->getValue());

        self::assertInstanceOf(RecordPreparedEvent::class, $events[6]);
        self::assertSame($events[4]->entity, $events[6]->entity);
        self::assertSame(2, $events[6]->rowNumber);

        self::assertInstanceOf(RecordPreparedEvent::class, $events[3]);
        self::assertInstanceOf(AfterImportEvent::class, $events[9]);
        self::assertSame([$events[3]->entity, $events[6]->entity], $events[9]->entities);
    }

    public function testValueIsChangedByListener(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(RecordValueEvent::class, static function (RecordValueEvent $event): void {
            $event->setValue(strtoupper((string) $event->getValue()));
        });
        $eventDispatcher->addListener(RecordPreparedEvent::class, static function (RecordPreparedEvent $event): void {
            self::assertInstanceOf(TestEntity::class, $event->entity);
            self::assertSame('VALUE', $event->entity->getTestPrivateProperty());
            $event->entity->testPublicProperty = 'set by listener';
        });

        $entity = new TestEntity();
        $this->createImporter($this->createEntityManager(['id' => 1]), $eventDispatcher)->import($this->createMatrix(), $this->createConfiguration($entity));

        self::assertSame('VALUE', $entity->getTestPrivateProperty());
        self::assertSame('set by listener', $entity->testPublicProperty);
    }

    public function testExceptionOfListenerBeforeSaveRevertsImport(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(RecordPreparedEvent::class, static function (): never {
            throw new RuntimeException('Invalid record.');
        });

        $entityManager = $this->createEntityManager(['id' => 1]);
        // entity is not persisted yet, when listener is called, and import is not saved
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $this->expectExceptionObject(new RuntimeException('Invalid record.'));
        $this->createImporter($entityManager, $eventDispatcher)->import($this->createMatrix(), new BaseConfiguration());
    }

    private function createImporter(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher): EntityImporter
    {
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturnCallback(
            static fn (string $class): ?EntityManagerInterface => TestEntity::class === $class ? $entityManager : null,
        );

        return new EntityImporter($managerRegistry, $eventDispatcher, $this->createStub(RelatedEntityFinder::class));
    }

    /**
     * @param array<string, mixed> $identifier
     */
    private function createEntityManager(array $identifier): EntityManagerInterface&MockObject
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityIdentifier')->willReturn($identifier);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        return $entityManager;
    }

    private function createConfiguration(TestEntity $entity): BaseConfiguration
    {
        return new class($entity) extends BaseConfiguration {
            public function __construct(private readonly TestEntity $entity)
            {
            }

            public function getNewEntity(MatrixRecord $record): object
            {
                return $this->entity;
            }
        };
    }

    private function createMatrix(): Matrix
    {
        return new Matrix(['test_private_property'], [['test_private_property' => 'value']]);
    }
}

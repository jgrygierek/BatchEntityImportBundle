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
use JG\BatchEntityImportBundle\Event\RecordImportedSuccessfullyEvent;
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
use stdClass;
use Stringable;
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
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (object $event) use (&$events): object {
            $events[] = $event;

            return $event;
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
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willThrowException($exception);

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
        $this->createImporter($entityManager, $this->createStub(EventDispatcherInterface::class))->import($this->createMatrix(), new BaseConfiguration());
    }

    public function testNewEntityIsCreatedByConfiguration(): void
    {
        $entity = new TestEntity();
        $entityManager = $this->createEntityManager(['id' => 1]);
        $entityManager->expects($this->once())->method('persist')->with($this->identicalTo($entity));

        $configuration = new class($entity) extends BaseConfiguration {
            public function __construct(private readonly TestEntity $entity)
            {
            }

            public function getNewEntity(MatrixRecord $record): object
            {
                return $this->entity;
            }
        };

        $this->createImporter($entityManager, $this->createStub(EventDispatcherInterface::class))->import($this->createMatrix(), $configuration);

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
        $this->createImporter($this->createEntityManager([]), $this->createStub(EventDispatcherInterface::class))->import(new Matrix(), $configuration);
    }

    public static function notManagedClassProvider(): Generator
    {
        yield 'class without manager' => [self::class];
        yield 'not existing class' => ['App\Entity\NotExisting'];
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

    private function createMatrix(): Matrix
    {
        return new Matrix(['test_private_property'], [['test_private_property' => 'value']]);
    }
}

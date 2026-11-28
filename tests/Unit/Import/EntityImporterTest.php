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
use JG\BatchEntityImportBundle\Exception\BatchEntityImportExceptionInterface;
use JG\BatchEntityImportBundle\Exception\DatabaseException;
use JG\BatchEntityImportBundle\Exception\MatrixRecordInvalidDataTypeException;
use JG\BatchEntityImportBundle\Form\Type\ArrayTextType;
use JG\BatchEntityImportBundle\Import\EntityImporter;
use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\AllTestEntityColumnsAllowedTrait;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Form\Type\CustomArrayTextType;
use JG\BatchEntityImportBundle\Tests\Fixtures\Model\PropertyAccessEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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

        $configuration = new BaseConfiguration();
        $this->createImporter($this->createEntityManager($identifier), $eventDispatcher)->import($this->createMatrix(), $configuration);

        self::assertCount(1, $events);
        self::assertSame($configuration, $events[0]->configuration);
        self::assertInstanceOf(TestEntity::class, $events[0]->entity);
        self::assertSame(TestEntity::class, $events[0]->class);
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

        $entityManager = $this->createEntityManagerMock(['id' => 1]);
        $entityManager->expects($this->once())->method('flush');

        // data is already saved, so original exception of listener is thrown, not DatabaseException
        $this->expectExceptionObject($exception);
        $this->createImporter($entityManager, $eventDispatcher)->import($this->createMatrix(), new BaseConfiguration());
    }

    /**
     * Data is already saved, so exception of this bundle can't be reported as error of import,
     * otherwise the preview would be shown again and the same data could be saved twice.
     */
    #[DataProvider('eventAfterSaveProvider')]
    public function testExceptionOfThisBundleThrownAfterSaveIsNotReportedAsErrorOfImport(string $eventClass): void
    {
        $exception = new MatrixRecordInvalidDataTypeException(1, 'test_private_property');
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener($eventClass, static function () use ($exception): never {
            throw $exception;
        });

        $entityManager = $this->createEntityManagerMock(['id' => 1]);
        $entityManager->expects($this->once())->method('flush');

        try {
            $this->createImporter($entityManager, $eventDispatcher)->import($this->createMatrix(), new BaseConfiguration());
            self::fail('Exception was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertNotInstanceOf(BatchEntityImportExceptionInterface::class, $runtimeException);
            self::assertSame($exception, $runtimeException->getPrevious());
            self::assertStringContainsString('Data is already saved', $runtimeException->getMessage());
        }
    }

    public static function eventAfterSaveProvider(): Generator
    {
        yield [RecordImportedSuccessfullyEvent::class];
        yield [AfterImportEvent::class];
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
        $entityManager = $this->createEntityManagerMock(['id' => 1]);
        $entityManager->expects($this->once())->method('persist')->with($this->identicalTo($entity));

        $this->createImporter($entityManager, new EventDispatcher())->import($this->createMatrix(), $this->createConfiguration($entity));

        self::assertSame('value', $entity->getTestPrivateProperty());
    }

    #[DataProvider('notManagedClassProvider')]
    public function testNotManagedEntityException(string $class): void
    {
        $configuration = new class($class) extends AbstractImportConfiguration {
            use AllTestEntityColumnsAllowedTrait;

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
        self::assertInstanceOf(RecordImportedSuccessfullyEvent::class, $events[7]);
        self::assertSame($configuration, $events[7]->configuration);
        self::assertSame($events[3]->entity, $events[7]->entity);
        self::assertInstanceOf(RecordImportedSuccessfullyEvent::class, $events[8]);
        self::assertSame($events[6]->entity, $events[8]->entity);

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

        $entityManager = $this->createEntityManagerMock(['id' => 1]);
        // entity is not persisted yet, when listener is called, and import is not saved
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $this->expectExceptionObject(new RuntimeException('Invalid record.'));
        $this->createImporter($entityManager, $eventDispatcher)->import($this->createMatrix(), new BaseConfiguration());
    }

    /**
     * Columns which can't be written from outside of the class are skipped, instead of error, which can't be shown to user.
     */
    public function testOnlyPropertiesWritableFromOutsideAreImported(): void
    {
        $entity = new PropertyAccessEntity();
        $columns = [
            'public_property',
            'upper_case_property',
            'property_with_protected_setter',
            'static_property',
            'readonly_property',
            'private_set_property',
            'protected_set_property',
            'get_only_property',
            'private_property_with_protected_setter',
        ];
        $configuration = new class($entity, $columns) extends AbstractImportConfiguration {
            /**
             * @param array<string> $columns
             */
            public function __construct(private readonly PropertyAccessEntity $entity, private readonly array $columns)
            {
            }

            public function getEntityClassName(): string
            {
                return PropertyAccessEntity::class;
            }

            public function getAllowedColumns(): array
            {
                // all columns are allowed, so only access to properties decides if column is imported
                return $this->columns;
            }

            public function getNewEntity(MatrixRecord $record): PropertyAccessEntity
            {
                return $this->entity;
            }
        };
        $matrix = new Matrix($columns, [array_fill_keys($columns, 'value')]);

        $this->createImporter($this->createEntityManager(['id' => 1]), new EventDispatcher())->import($matrix, $configuration);

        self::assertSame('value', $entity->publicProperty);
        self::assertSame('VALUE', $entity->upperCaseProperty);
        // setter is not public, so value is set directly to property
        self::assertSame('value', $entity->propertyWithProtectedSetter);
        self::assertSame('', PropertyAccessEntity::$staticProperty);
        self::assertSame('readonly', $entity->readonlyProperty);
        self::assertSame('', $entity->privateSetProperty);
        self::assertSame('', $entity->protectedSetProperty);
        self::assertSame('get only', $entity->getOnlyProperty);
        self::assertSame('', $entity->getPrivatePropertyWithProtectedSetter());
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string> $expected
     */
    #[DataProvider('arrayValueProvider')]
    public function testArrayValueIsSplitBySeparator(array $options, string $value, array $expected): void
    {
        self::assertSame($expected, $this->importArrayValue(ArrayTextType::class, $options, $value));
    }

    /**
     * Type can be extended in application, so value of its subclass is imported as array too,
     * and options are resolved by the type, the same way as in the preview.
     *
     * @param array<string, mixed> $options
     * @param array<string> $expected
     */
    #[DataProvider('subclassArrayValueProvider')]
    public function testArrayValueOfSubclassIsSplitBySeparator(array $options, array $expected): void
    {
        self::assertSame($expected, $this->importArrayValue(CustomArrayTextType::class, $options, 'a;b|c'));
    }

    public static function subclassArrayValueProvider(): Generator
    {
        yield 'default separator of subclass' => [[], ['a', 'b|c']];
        yield 'separator from field definition' => [['separator' => '|'], ['a;b', 'c']];
    }

    public static function arrayValueProvider(): Generator
    {
        yield 'default separator' => [[], 'a|b|c', ['a', 'b', 'c']];
        yield 'default separator, empty values' => [[], '||', ['', '', '']];
        yield 'default separator, empty last value' => [[], 'a|b|', ['a', 'b', '']];
        yield 'default separator, empty value in the middle' => [[], 'a||c', ['a', '', 'c']];
        yield 'default separator, empty first value' => [[], '|b|c', ['', 'b', 'c']];
        yield 'default separator, other separator in value' => [[], 'a;b;c', ['a;b;c']];
        yield 'empty value' => [[], '', []];
        yield 'whitespace' => [[], ' ', [' ']];
        yield 'zero' => [[], '0', ['0']];
        yield 'zeros' => [[], '0|0', ['0', '0']];
        yield 'custom separator' => [['separator' => ';'], 'a;b;c', ['a', 'b', 'c']];
        yield 'custom separator, empty values' => [['separator' => ';'], ';;', ['', '', '']];
        yield 'custom separator, default separator in value' => [['separator' => ';'], 'a|b|c', ['a|b|c']];
        yield 'empty separator is replaced by default one' => [['separator' => ''], 'a|b|c', ['a', 'b', 'c']];
        yield 'null separator is replaced by default one' => [['separator' => null], 'a|b|c', ['a', 'b', 'c']];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed> value of array field after import
     */
    private function importArrayValue(string $fieldClass, array $options, string $value): array
    {
        $entity = new TestEntity();
        $configuration = new class($entity, new FormFieldDefinition($fieldClass, $options)) extends AbstractImportConfiguration {
            use AllTestEntityColumnsAllowedTrait;

            public function __construct(private readonly TestEntity $entity, private readonly FormFieldDefinition $fieldDefinition)
            {
            }

            public function getEntityClassName(): string
            {
                return TestEntity::class;
            }

            public function getFieldsDefinitions(): array
            {
                return ['test_array_field' => $this->fieldDefinition];
            }

            public function getNewEntity(MatrixRecord $record): TestEntity
            {
                return $this->entity;
            }
        };
        $matrix = new Matrix(['test_array_field'], [['test_array_field' => $value]]);

        $this->createImporter($this->createEntityManager(['id' => 1]), new EventDispatcher())->import($matrix, $configuration);

        return $entity->getTestArrayField();
    }

    private function createImporter(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher): EntityImporter
    {
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturnCallback(
            static fn (string $class): ?EntityManagerInterface => \in_array($class, [TestEntity::class, PropertyAccessEntity::class], true) ? $entityManager : null,
        );

        // types are resolved the same way as in application, where they are registered as services
        $translator = $this->createStub(TranslatorInterface::class);
        $formRegistry = new FormRegistry(
            [new CoreExtension(), new PreloadedExtension([new ArrayTextType($translator), new CustomArrayTextType($translator)], [])],
            new ResolvedFormTypeFactory(),
        );

        return new EntityImporter($managerRegistry, $eventDispatcher, $this->createStub(RelatedEntityFinder::class), $formRegistry);
    }

    /**
     * @param array<string, mixed> $identifier
     */
    private function createEntityManager(array $identifier): EntityManagerInterface&Stub
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $this->configureEntityManager($entityManager, $identifier);

        return $entityManager;
    }

    /**
     * Used only by tests which check calls of entity manager.
     *
     * @param array<string, mixed> $identifier
     */
    private function createEntityManagerMock(array $identifier): EntityManagerInterface&MockObject
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->configureEntityManager($entityManager, $identifier);

        return $entityManager;
    }

    /**
     * @param array<string, mixed> $identifier
     */
    private function configureEntityManager(EntityManagerInterface&Stub $entityManager, array $identifier): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityIdentifier')->willReturn($identifier);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
    }

    private function createConfiguration(TestEntity $entity): BaseConfiguration
    {
        return new class($entity) extends BaseConfiguration {
            public function __construct(private readonly TestEntity $entity)
            {
            }

            public function getNewEntity(MatrixRecord $record): TestEntity
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

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional\Model\Configuration;

use Doctrine\ORM\EntityManagerInterface;
use Generator;
use JG\BatchEntityImportBundle\Event\RecordImportedSuccessfullyEvent;
use JG\BatchEntityImportBundle\Exception\DatabaseNotUniqueDataException;
use JG\BatchEntityImportBundle\Exception\MatrixRecordInvalidDataTypeException;
use JG\BatchEntityImportBundle\Exception\MatrixRecordRelatedEntityNotFoundException;
use JG\BatchEntityImportBundle\Form\Type\EntityLookupType;
use JG\BatchEntityImportBundle\Import\EntityImporter;
use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\AllowedColumnsConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\EntityLookupConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\EntityLookupTextConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestRelatedEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Event\TestableEventDispatcher;
use JG\BatchEntityImportBundle\Tests\Functional\AbstractWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;
use TypeError;

class ImportConfigurationTest extends AbstractWebTestCase
{
    private EntityManagerInterface $entityManager;
    private EntityImporter $importer;
    /** @var TestableEventDispatcher */
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::$kernel->getContainer()->get('doctrine.orm.entity_manager');
        $this->eventDispatcher = self::getTestableEventDispatcher();
        $this->importer = self::getService(EntityImporter::class);
        $this->eventDispatcher->resetDispatchedEvents();

        $databaseLoader = self::$kernel->getContainer()->get(DatabaseLoader::class);
        $databaseLoader->reload();
    }

    public function testBasicMethods(): void
    {
        $config = new class extends AbstractImportConfiguration {
            public function getEntityClassName(): string
            {
                return TestEntity::class;
            }
        };

        self::assertNull($config->getEntityTranslationRelationName());
        self::assertEmpty($config->getMatrixConstraints());
        self::assertEmpty($config->getFieldsDefinitions());
        self::assertSame(['csv', 'xls', 'xlsx', 'ods'], $config->getAllowedFileExtensions());
    }

    #[DataProvider('matrixDataProvider')]
    public function testItemImportedSuccessfully(array $header, array $records): void
    {
        $repository = $this->entityManager->getRepository(TestEntity::class);
        self::assertEmpty($repository->findAll());

        $matrix = new Matrix($header, $records);

        $config = new BaseConfiguration();
        $this->importer->import($matrix, $config);

        self::assertCount(2, $repository->findAll());

        /** @var TestEntity|null $item */
        $item = $repository->find(1);

        self::assertNotEmpty($item);
        self::assertSame('value_2', $item->getTestPrivateProperty());
        self::assertSame('public_value_1', $item->testPublicProperty);

        /** @var TestEntity|null $item */
        $item = $repository->find(2);

        self::assertNotEmpty($item);
        self::assertSame('value_4', $item->getTestPrivateProperty());
        self::assertSame('public_value_2', $item->testPublicProperty);

        $dispatchedEvents = $this->eventDispatcher->getEventsFor(RecordImportedSuccessfullyEvent::class);
        self::assertCount(2, $dispatchedEvents);

        self::assertInstanceOf(RecordImportedSuccessfullyEvent::class, $dispatchedEvents[0]);
        self::assertSame(TestEntity::class, $dispatchedEvents[0]->class);
        self::assertSame(1, $dispatchedEvents[0]->id);
        self::assertSame($repository->find(1), $dispatchedEvents[0]->entity);
        self::assertSame($config, $dispatchedEvents[0]->configuration);

        self::assertInstanceOf(RecordImportedSuccessfullyEvent::class, $dispatchedEvents[1]);
        self::assertSame(TestEntity::class, $dispatchedEvents[1]->class);
        self::assertSame(2, $dispatchedEvents[1]->id);
    }

    public static function matrixDataProvider(): Generator
    {
        yield 'columns names with pascalCase' => [
            [
                'unknownColumn',
                'testPrivateProperty',
                'testPublicProperty',
            ],
            [
                [
                    'unknownColumn' => 'value_1',
                    'testPrivateProperty' => 'value_2',
                    'testPublicProperty' => 'public_value_1',
                ],
                [
                    'unknownColumn' => 'value_3',
                    'testPrivateProperty' => 'value_4',
                    'testPublicProperty' => 'public_value_2',
                ],
            ],
        ];

        yield 'columns names with CamelCase' => [
            [
                'UnknownColumn',
                'TestPrivateProperty',
                'TestPublicProperty',
            ],
            [
                [
                    'UnknownColumn' => 'value_1',
                    'TestPrivateProperty' => 'value_2',
                    'TestPublicProperty' => 'public_value_1',
                ],
                [
                    'UnknownColumn' => 'value_3',
                    'TestPrivateProperty' => 'value_4',
                    'TestPublicProperty' => 'public_value_2',
                ],
            ],
        ];

        yield 'columns names with underscore' => [
            [
                'unknown_column',
                'test_private_property',
                'test_public_property',
            ],
            [
                [
                    'unknown_column' => 'value_1',
                    'test_private_property' => 'value_2',
                    'test_public_property' => 'public_value_1',
                ],
                [
                    'unknown_column' => 'value_3',
                    'test_private_property' => 'value_4',
                    'test_public_property' => 'public_value_2',
                ],
            ],
        ];

        yield 'columns names with with dash' => [
            [
                'unknown-column',
                'test-private-property',
                'test-private-property-dash',
                'test-public-property',
            ],
            [
                [
                    'unknown-column' => 'value_1',
                    'test-private-property' => 'value_2',
                    'test-private-property-dash' => 'value_2',
                    'test-public-property' => 'public_value_1',
                ],
                [
                    'unknown-column' => 'value_3',
                    'test-private-property' => 'value_4',
                    'test-private-property-dash' => 'value_4',
                    'test-public-property' => 'public_value_2',
                ],
            ],
        ];

        yield 'columns names with with space' => [
            [
                'unknown column',
                'test private property',
                'test public property',
            ],
            [
                [
                    'unknown column' => 'value_1',
                    'test private property' => 'value_2',
                    'test public property' => 'public_value_1',
                ],
                [
                    'unknown column' => 'value_3',
                    'test private property' => 'value_4',
                    'test public property' => 'public_value_2',
                ],
            ],
        ];
    }

    #[DataProvider('exceptionCheckProvider')]
    public function testExceptionsDuringImport(string $expectedExceptionClass, array $data): void
    {
        $repository = $this->entityManager->getRepository(TestEntity::class);
        self::assertEmpty($repository->findAll());

        $matrix = new Matrix(['test_private_property'], $data);

        $config = new BaseConfiguration();

        $throwable = null;
        try {
            $this->importer->import($matrix, $config);
        } catch (Throwable $throwable) {
        }
        self::assertInstanceOf($expectedExceptionClass, $throwable);
        self::assertFalse($this->eventDispatcher->hasEvent(RecordImportedSuccessfullyEvent::class));
    }

    public static function exceptionCheckProvider(): Generator
    {
        yield [
            'expectedExceptionClass' => MatrixRecordInvalidDataTypeException::class,
            'data' => [
                [
                    'test_private_property' => 1,
                ],
            ],
        ];
        yield [
            'expectedExceptionClass' => DatabaseNotUniqueDataException::class,
            'data' => [
                [
                    'test_private_property' => 'value1',
                ],
                [
                    'test_private_property' => 'value1',
                ],
            ],
        ];
    }

    public function testOnlyAllowedColumnsAreImported(): void
    {
        $matrix = new Matrix(
            ['test_private_property', 'test_public_property', 'testPrivateProperty2'],
            [
                ['test_private_property' => 'value_1', 'test_public_property' => 'public_1', 'testPrivateProperty2' => 'private2_1'],
                ['test_private_property' => 'value_2', 'test_public_property' => 'public_2', 'testPrivateProperty2' => 'private2_2'],
            ],
        );

        $config = new AllowedColumnsConfiguration();
        $this->importer->import($matrix, $config);

        $this->entityManager->clear();

        $items = $this->entityManager->getRepository(TestEntity::class)->findBy([], ['id' => 'ASC']);
        self::assertCount(2, $items);
        foreach ($items as $index => $item) {
            self::assertSame('value_' . ($index + 1), $item->getTestPrivateProperty());
            // these columns are not allowed, so properties have default values
            self::assertSame('', $item->testPublicProperty);
            self::assertSame('', $item->getTestPrivateProperty2());
        }
    }

    public function testAllColumnsAreAllowedByDefault(): void
    {
        self::assertSame([], new BaseConfiguration()->getAllowedColumns());
    }

    public function testInvalidDataTypeExceptionContainsPosition(): void
    {
        $matrix = new Matrix(
            ['test_private_property', 'test_public_property'],
            [
                ['test_private_property' => 'first', 'test_public_property' => 'first_public'],
                ['test_private_property' => 'second', 'test_public_property' => 'second_public'],
                // null can't be set to string property
                ['test_private_property' => 'third', 'test_public_property' => null],
            ],
        );

        $config = new BaseConfiguration();
        try {
            $this->importer->import($matrix, $config);
            self::fail('Exception should be thrown.');
        } catch (MatrixRecordInvalidDataTypeException $matrixRecordInvalidDataTypeException) {
        }

        self::assertSame('exception.matrix.record.data.invalid_type_in_field', $matrixRecordInvalidDataTypeException->getMessage());
        self::assertSame(['%row%' => 3, '%column%' => 'test_public_property'], $matrixRecordInvalidDataTypeException->getTranslationParameters());
        self::assertInstanceOf(TypeError::class, $matrixRecordInvalidDataTypeException->getPrevious());
    }

    public function testFailedImportIsNotSavedByLaterFlush(): void
    {
        $existingEntity = new TestEntity();
        $existingEntity->setTestPrivateProperty('existing');
        $existingEntity->testPublicProperty = 'existing_public';

        $this->entityManager->persist($existingEntity);
        $this->entityManager->flush();

        $matrix = new Matrix(
            ['test_private_property', 'test_public_property'],
            [
                ['test_private_property' => 'changed', 'test_public_property' => 'changed_public'],
                ['test_private_property' => 'new', 'test_public_property' => 'new_public'],
                // null can't be set to string property
                ['test_private_property' => 'invalid', 'test_public_property' => null],
            ],
        );
        $matrix->getRecords()[0]->setEntity($existingEntity);

        $config = new BaseConfiguration();
        try {
            $this->importer->import($matrix, $config);
            self::fail('Exception should be thrown.');
        } catch (MatrixRecordInvalidDataTypeException) {
        }

        self::assertSame('existing', $existingEntity->getTestPrivateProperty());
        self::assertSame('existing_public', $existingEntity->testPublicProperty);

        // e.g. flush called by some listener later in the same request
        $this->entityManager->flush();
        $this->entityManager->clear();

        $items = $this->entityManager->getRepository(TestEntity::class)->findAll();
        self::assertCount(1, $items);
        self::assertSame('existing', $items[0]->getTestPrivateProperty());
        self::assertSame('existing_public', $items[0]->testPublicProperty);
    }

    public function testImportFailedWithOtherExceptionIsNotSavedByLaterFlush(): void
    {
        $matrix = new Matrix(
            ['test_private_property'],
            [['test_private_property' => 'value_1'], ['test_private_property' => 'value_2']],
        );

        $config = new class extends BaseConfiguration {
            public function getNewEntity(MatrixRecord $record): object
            {
                if ('value_2' === $record->getData()['test_private_property']) {
                    throw new RuntimeException('Not an exception of this bundle.');
                }

                return parent::getNewEntity($record);
            }
        };

        try {
            $this->importer->import($matrix, $config);
            self::fail('Exception should be thrown.');
        } catch (RuntimeException) {
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertCount(0, $this->entityManager->getRepository(TestEntity::class)->findAll());
    }

    /**
     * @param class-string<AbstractImportConfiguration> $configurationClass
     */
    #[DataProvider('entityLookupConfigurationProvider')]
    public function testRelatedEntityImportedSuccessfully(string $configurationClass): void
    {
        $this->entityManager->persist(new TestRelatedEntity('first'));
        $this->entityManager->persist(new TestRelatedEntity('second'));
        $this->entityManager->flush();

        $matrix = new Matrix(
            ['test_private_property', 'test_related_entity'],
            [
                ['test_private_property' => 'value_1', 'test_related_entity' => 'second'],
                ['test_private_property' => 'value_2', 'test_related_entity' => 'first'],
                ['test_private_property' => 'value_3', 'test_related_entity' => 'second'],
                ['test_private_property' => 'value_4', 'test_related_entity' => ''],
            ],
        );

        $config = new $configurationClass();
        $this->importer->import($matrix, $config);

        $this->entityManager->clear();

        $repository = $this->entityManager->getRepository(TestEntity::class);
        $relatedNames = [];
        foreach ($repository->findBy([], ['id' => 'ASC']) as $item) {
            $relatedNames[$item->getTestPrivateProperty()] = $item->getTestRelatedEntity()?->getName();
        }

        self::assertSame(
            ['value_1' => 'second', 'value_2' => 'first', 'value_3' => 'second', 'value_4' => null],
            $relatedNames,
        );
    }

    /**
     * @param class-string<AbstractImportConfiguration> $configurationClass
     */
    #[DataProvider('entityLookupConfigurationProvider')]
    public function testRelatedEntityNotFound(string $configurationClass): void
    {
        $this->entityManager->persist(new TestRelatedEntity('first'));
        $this->entityManager->flush();

        $matrix = new Matrix(
            ['test_private_property', 'test_related_entity'],
            [
                ['test_private_property' => 'value_1', 'test_related_entity' => 'first'],
                ['test_private_property' => 'value_2', 'test_related_entity' => 'unknown'],
            ],
        );

        $config = new $configurationClass();

        try {
            $this->importer->import($matrix, $config);
            self::fail('Exception should be thrown.');
        } catch (MatrixRecordRelatedEntityNotFoundException $matrixRecordRelatedEntityNotFoundException) {
        }

        self::assertSame('exception.matrix.record.related_entity.not_found_in_field', $matrixRecordRelatedEntityNotFoundException->getMessage());
        self::assertSame(['%row%' => 2, '%column%' => 'test_related_entity'], $matrixRecordRelatedEntityNotFoundException->getTranslationParameters());
    }

    public static function entityLookupConfigurationProvider(): Generator
    {
        yield 'select' => [EntityLookupConfiguration::class];
        yield 'text' => [EntityLookupTextConfiguration::class];
    }

    public function testImportCalledTwiceOnSameConfiguration(): void
    {
        $this->entityManager->persist(new TestRelatedEntity('first'));
        $this->entityManager->flush();

        $config = new EntityLookupConfiguration();

        $this->importer->import(new Matrix(
            ['test_private_property', 'test_related_entity'],
            [['test_private_property' => 'value_1', 'test_related_entity' => 'first']],
        ), $config);
        self::assertCount(1, $this->eventDispatcher->getEventsFor(RecordImportedSuccessfullyEvent::class));

        // simulates next request in long-running process: related entity cached in first import is detached now
        $this->entityManager->clear();
        $this->eventDispatcher->resetDispatchedEvents();

        $this->importer->import(new Matrix(
            ['test_private_property', 'test_related_entity'],
            [['test_private_property' => 'value_2', 'test_related_entity' => 'first']],
        ), $config);

        $dispatchedEvents = $this->eventDispatcher->getEventsFor(RecordImportedSuccessfullyEvent::class);
        self::assertCount(1, $dispatchedEvents);
        self::assertInstanceOf(RecordImportedSuccessfullyEvent::class, $dispatchedEvents[0]);
        self::assertSame(2, $dispatchedEvents[0]->id);

        $this->entityManager->clear();
        $repository = $this->entityManager->getRepository(TestEntity::class);
        self::assertCount(2, $repository->findAll());
        self::assertSame('first', $repository->find(2)?->getTestRelatedEntity()?->getName());
        self::assertCount(1, $this->entityManager->getRepository(TestRelatedEntity::class)->findAll());
    }

    public function testRelatedEntityImportedByNumericValues(): void
    {
        $this->entityManager->persist(new TestRelatedEntity('first'));
        $this->entityManager->persist(new TestRelatedEntity('second'));
        $this->entityManager->flush();

        $config = new class extends AbstractImportConfiguration {
            public function getEntityClassName(): string
            {
                return TestEntity::class;
            }

            public function getFieldsDefinitions(): array
            {
                return [
                    'test_related_entity' => new FormFieldDefinition(EntityLookupType::class, ['class' => TestRelatedEntity::class]),
                ];
            }
        };

        // MatrixRecord accepts any scalar, so import() must not fail with TypeError for float value
        $records = [
            ['test_private_property' => 'value_1', 'test_related_entity' => 2.0],
            ['test_private_property' => 'value_2', 'test_related_entity' => 1],
        ];
        $this->importer->import(new Matrix(['test_private_property', 'test_related_entity'], $records), $config);
        $this->entityManager->clear();

        $repository = $this->entityManager->getRepository(TestEntity::class);
        self::assertSame('second', $repository->find(1)?->getTestRelatedEntity()?->getName());
        self::assertSame('first', $repository->find(2)?->getTestRelatedEntity()?->getName());
    }
}

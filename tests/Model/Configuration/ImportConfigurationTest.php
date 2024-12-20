<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Model\Configuration;

use Doctrine\ORM\EntityManagerInterface;
use Generator;
use JG\BatchEntityImportBundle\Exception\DatabaseNotUniqueDataException;
use JG\BatchEntityImportBundle\Exception\MatrixRecordInvalidDataTypeException;
use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ImportConfigurationTest extends WebTestCase
{
    protected ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::$kernel->getContainer()->get('doctrine.orm.entity_manager');
        $databaseLoader = self::$kernel->getContainer()->get(DatabaseLoader::class);
        $databaseLoader->reload();
    }

    public function testBasicMethods(): void
    {
        $config = $this->getMockBuilder(AbstractImportConfiguration::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getMatrixConstraints', 'getFieldsDefinitions', 'getAllowedFileExtensions'])
            ->getMock();

        self::assertNull($config->getEntityTranslationRelationName());
        self::assertEmpty($config->getMatrixConstraints());
        self::assertEmpty($config->getFieldsDefinitions());
        self::assertSame(['csv', 'xls', 'xlsx', 'ods'], $config->getAllowedFileExtensions());
    }

    /**
     * @dataProvider matrixDataProvider
     */
    public function testItemImportedSuccessfully(array $header, array $records): void
    {
        $repository = $this->entityManager->getRepository(TestEntity::class);
        self::assertEmpty($repository->findAll());

        $matrix = new Matrix($header, $records);

        $config = new BaseConfiguration($this->entityManager);
        $config->import($matrix);

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

    /**
     * @dataProvider exceptionCheckProvider
     */
    public function testExceptionsDuringImport(string $exceptionClass, array $data): void
    {
        $this->expectException($exceptionClass);

        $repository = $this->entityManager->getRepository(TestEntity::class);
        self::assertEmpty($repository->findAll());

        $matrix = new Matrix(['test_private_property'], $data);

        $config = new BaseConfiguration($this->entityManager);
        $config->import($matrix);
    }

    public static function exceptionCheckProvider(): Generator
    {
        yield [
            'exception_class' => MatrixRecordInvalidDataTypeException::class,
            'data' => [
                [
                    'test_private_property' => 1,
                ],
            ],
        ];
        yield [
            'exception_class' => DatabaseNotUniqueDataException::class,
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
}

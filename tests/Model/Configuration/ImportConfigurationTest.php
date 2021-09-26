<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Model\Configuration;

use Doctrine\ORM\EntityManagerInterface;
use Generator;
use JG\BatchEntityImportBundle\Exception\DatabaseNotUniqueDataException;
use JG\BatchEntityImportBundle\Exception\MatrixRecordInvalidDataTypeException;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\TranslatableEntityBaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;
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

    public function testItemImportedSuccessfully(): void
    {
        $repository = $this->entityManager->getRepository(TestEntity::class);
        self::assertEmpty($repository->findAll());

        $matrix = new Matrix(
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
            ]
        );

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

    public function testTranslatableItemImportedSuccessfully(): void
    {
        $repository = $this->entityManager->getRepository(TranslatableEntity::class);
        self::assertEmpty($repository->findAll());

        $matrix = new Matrix(
            [
                'unknown_column',
                'test_private_property',
                'test_public_property',
                'test_translation_property:en',
                'test_translation_property:pl',
            ],
            [
                [
                    'unknown_column' => 'value_1',
                    'test_private_property' => 'value_2',
                    'test_public_property' => 'public_value_1',
                    'test_translation_property:en' => 'value_3',
                    'test_translation_property:pl' => 'value_4',
                ],
                [
                    'unknown_column' => 'value_5',
                    'test_private_property' => 'value_6',
                    'test_public_property' => 'public_value_2',
                    'test_translation_property:en' => 'value_7',
                    'test_translation_property:pl' => 'value_8',
                ],
            ]
        );

        $config = new TranslatableEntityBaseConfiguration($this->entityManager);
        $config->import($matrix);

        self::assertCount(2, $repository->findAll());

        /** @var TranslatableEntity|null $item */
        $item = $repository->find(1);

        self::assertNotEmpty($item);
        self::assertSame('value_2', $item->getTestPrivateProperty());
        self::assertSame('value_3', $item->translate('en')->getTestTranslationProperty());
        self::assertSame('value_4', $item->translate('pl')->getTestTranslationProperty());
        self::assertSame('public_value_1', $item->testPublicProperty);

        /** @var TranslatableEntity|null $item */
        $item = $repository->find(2);

        self::assertNotEmpty($item);
        self::assertSame('value_6', $item->getTestPrivateProperty());
        self::assertSame('value_7', $item->translate('en')->getTestTranslationProperty());
        self::assertSame('value_8', $item->translate('pl')->getTestTranslationProperty());
        self::assertSame('public_value_2', $item->testPublicProperty);
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

    public function exceptionCheckProvider(): Generator
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

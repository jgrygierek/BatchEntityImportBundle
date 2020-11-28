<?php

namespace JG\BatchEntityImportBundle\Tests\Model\Configuration;

use Doctrine\ORM\EntityManagerInterface;
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

    public function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::$kernel->getContainer()->get('doctrine.orm.entity_manager');
        $databaseLoader = self::$kernel->getContainer()->get(DatabaseLoader::class);
        $databaseLoader->reload();
    }

    public function testItemImportedSuccessfully(): void
    {
        $repository = $this->entityManager->getRepository(TestEntity::class);
        $this->assertEmpty($repository->find(1));

        $matrix = new Matrix(
            [
                'unknown_column',
                'test_property',
            ],
            [
                [
                    'unknown_column' => 'value_1',
                    'test_property' => 'value_2',
                ],
                [
                    'unknown_column' => 'value_3',
                    'test_property' => 'value_4',
                ],
            ]
        );

        $config = new BaseConfiguration($this->entityManager);
        $config->import($matrix);

        $this->assertCount(2, $repository->findAll());

        /** @var TestEntity|null $item */
        $item = $repository->find(1);

        $this->assertNotEmpty($item);
        $this->assertSame('value_2', $item->getTestProperty());

        /** @var TestEntity|null $item */
        $item = $repository->find(2);

        $this->assertNotEmpty($item);
        $this->assertSame('value_4', $item->getTestProperty());
    }

    public function testTranslatableItemImportedSuccessfully(): void
    {
        $repository = $this->entityManager->getRepository(TranslatableEntity::class);
        $this->assertEmpty($repository->find(1));

        $matrix = new Matrix(
            [
                'unknown_column',
                'test_property',
                'test_translation_property:en',
                'test_translation_property:pl',
            ],
            [
                [
                    'unknown_column' => 'value_1',
                    'test_property' => 'value_2',
                    'test_translation_property:en' => 'value_3',
                    'test_translation_property:pl' => 'value_4',
                ],
                [
                    'unknown_column' => 'value_5',
                    'test_property' => 'value_6',
                    'test_translation_property:en' => 'value_7',
                    'test_translation_property:pl' => 'value_8',
                ],
            ]
        );

        $config = new TranslatableEntityBaseConfiguration($this->entityManager);
        $config->import($matrix);

        $this->assertCount(2, $repository->findAll());

        /** @var TranslatableEntity|null $item */
        $item = $repository->find(1);

        $this->assertNotEmpty($item);
        $this->assertSame('value_2', $item->getTestProperty());
        $this->assertSame('value_3', $item->translate('en')->getTestTranslationProperty());
        $this->assertSame('value_4', $item->translate('pl')->getTestTranslationProperty());

        /** @var TranslatableEntity|null $item */
        $item = $repository->find(2);

        $this->assertNotEmpty($item);
        $this->assertSame('value_6', $item->getTestProperty());
        $this->assertSame('value_7', $item->translate('en')->getTestTranslationProperty());
        $this->assertSame('value_8', $item->translate('pl')->getTestTranslationProperty());
    }
}

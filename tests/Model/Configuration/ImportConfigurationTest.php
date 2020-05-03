<?php

namespace JG\BatchEntityImportBundle\Tests\Model\Configuration;

use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Tests\AbstractDatabaseTestCase;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\TranslatableEntityBaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;

class ImportConfigurationTest extends AbstractDatabaseTestCase
{
    public function testItemImportedSuccessfully(): void
    {
        $repository = $this->entityManager->getRepository(TestEntity::class);
        $this->assertEmpty($repository->find(1));

        $record = new MatrixRecord(
            [
                'unknown_column' => 'value_1',
                'test_property'  => 'value_2',
            ]
        );

        $config = new BaseConfiguration($this->entityManager);
        $config->prepareRecord($record);

        $this->entityManager->flush();

        /** @var TestEntity|null $item */
        $item = $repository->find(1);

        $this->assertNotEmpty($item);
        $this->assertSame('value_2', $item->getTestProperty());
    }

    public function testTranslatableItemImportedSuccessfully(): void
    {
        $repository = $this->entityManager->getRepository(TranslatableEntity::class);
        $this->assertEmpty($repository->find(1));

        $record = new MatrixRecord(
            [
                'unknown_column'               => 'value_1',
                'test_property'                => 'value_2',
                'test_translation_property:en' => 'value_3',
                'test_translation_property:pl' => 'value_4',
            ]
        );

        $config = new TranslatableEntityBaseConfiguration($this->entityManager);
        $config->prepareRecord($record);

        $this->entityManager->flush();

        /** @var TranslatableEntity|null $item */
        $item = $repository->find(1);

        $this->assertNotEmpty($item);
        $this->assertSame('value_2', $item->getTestProperty());
        $this->assertSame('value_3', $item->translate('en')->getTestTranslationProperty());
        $this->assertSame('value_4', $item->translate('pl')->getTestTranslationProperty());
    }
}

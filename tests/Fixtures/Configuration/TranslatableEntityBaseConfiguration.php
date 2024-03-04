<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Configuration;

use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;

class TranslatableEntityBaseConfiguration extends AbstractImportConfiguration
{
    public function getEntityClassName(): string
    {
        return TranslatableEntity::class;
    }

    public function getEntityTranslationRelationName(): ?string
    {
        return 'translations';
    }

    public function getNewEntity(MatrixRecord $record): object
    {
        $data = $record->getData();
        $existingEntity = !empty($data['id']) ? $this->getRepository()->find($data['id']) : null;

        if (!$existingEntity) {
            return parent::getNewEntity($record);
        }

        return $existingEntity;
    }
}

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Configuration;

use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
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
}

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Configuration;

use JG\BatchEntityImportBundle\Form\Type\EntityLookupTextType;
use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestRelatedEntity;

class EntityLookupTextConfiguration extends AbstractImportConfiguration
{
    public function getEntityClassName(): string
    {
        return TestEntity::class;
    }

    public function getFieldsDefinitions(): array
    {
        return [
            'test_related_entity' => new FormFieldDefinition(
                EntityLookupTextType::class,
                [
                    'class' => TestRelatedEntity::class,
                    'lookup_field' => 'name',
                ],
            ),
        ];
    }
}

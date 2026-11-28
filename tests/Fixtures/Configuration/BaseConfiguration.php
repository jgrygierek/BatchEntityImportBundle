<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Configuration;

use JG\BatchEntityImportBundle\Form\Type\ArrayTextType;
use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Validator\Constraints\DatabaseEntityUnique;

class BaseConfiguration extends AbstractImportConfiguration
{
    use AllTestEntityColumnsAllowedTrait;

    /**
     * Updating of entities is tested with this configuration.
     */
    public function allowOverrideEntity(): bool
    {
        return true;
    }

    public function getEntityClassName(): string
    {
        return TestEntity::class;
    }

    public function getFieldsDefinitions(): array
    {
        return [
            'test_array_field' => new FormFieldDefinition(ArrayTextType::class),
        ];
    }

    public function getMatrixConstraints(): array
    {
        return [
            new DatabaseEntityUnique(
                entityClassName: $this->getEntityClassName(),
                fields: [
                    'test_private_property',
                    'test_public_property',
                ],
            ),
            new DatabaseEntityUnique(
                entityClassName: $this->getEntityClassName(),
                fields: [
                    'test-private-property2',
                ],
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\KnpLabs\Fixtures\Configuration;

use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Tests\KnpLabs\Fixtures\Entity\TranslatableEntity;
use JG\BatchEntityImportBundle\Validator\Constraints\DatabaseEntityUnique;

class TranslatableEntityConfiguration extends AbstractImportConfiguration
{
    public function getEntityClassName(): string
    {
        return TranslatableEntity::class;
    }

    public function getAllowedColumns(): array
    {
        // also columns which can't be imported for other reasons (e.g. property without setter), so tests check these reasons
        return [
            'test_public_property',
            'test_private_property',
            'test_private_property2',
            'test_private_property_no_setter',
            'test_translation_property',
            'test_translation_array',
        ];
    }

    /**
     * Updating of entities is tested with this configuration.
     */
    public function allowOverrideEntity(): bool
    {
        return true;
    }

    public function getEntityTranslationRelationName(): ?string
    {
        return 'translations';
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

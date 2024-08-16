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

    public function getEntityTranslationRelationName(): ?string
    {
        return 'translations';
    }

    public function getMatrixConstraints(): array
    {
        return [
            new DatabaseEntityUnique([
                'entityClassName' => $this->getEntityClassName(),
                'fields' => [
                    'test_private_property',
                    'test_public_property',
                ],
            ]),
            new DatabaseEntityUnique([
                'entityClassName' => $this->getEntityClassName(),
                'fields' => [
                    'test-private-property2',
                ],
            ]),
        ];
    }
}

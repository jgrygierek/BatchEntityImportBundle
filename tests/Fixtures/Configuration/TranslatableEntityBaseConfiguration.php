<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Configuration;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;
use JG\BatchEntityImportBundle\Validator\Constraints\DatabaseEntityUnique;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslatableEntityBaseConfiguration extends AbstractImportConfiguration
{
    public function __construct(EntityManagerInterface $em, private readonly TranslatorInterface $translator)
    {
        parent::__construct($em);
    }

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
            new DatabaseEntityUnique(['entityClassName' => $this->getEntityClassName(), 'fields' => ['test_private_property']]),
        ];
    }
}

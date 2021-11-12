<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Configuration;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslatableEntityBaseConfiguration extends AbstractImportConfiguration
{
    private TranslatorInterface $translator;

    public function __construct(EntityManagerInterface $em, TranslatorInterface $translator)
    {
        parent::__construct($em);

        $this->translator = $translator;
    }

    public function getEntityClassName(): string
    {
        return TranslatableEntity::class;
    }

    public function getEntityTranslationRelationName(): ?string
    {
        return 'translations';
    }
}

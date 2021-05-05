<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

trait DependencyInjectionTrait
{
    protected ?TranslatorInterface $translator = null;
    protected ?EntityManagerInterface $em = null;
    protected ?ValidatorInterface $validator = null;

    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    public function setEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }
}

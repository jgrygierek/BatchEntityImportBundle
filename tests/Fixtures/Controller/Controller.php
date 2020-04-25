<?php

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Controller;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Controller\ImportControllerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class Controller implements ImportControllerInterface
{
    public function setTranslator(TranslatorInterface $translator): void
    {
    }

    public function setEntityManager(EntityManagerInterface $em): void
    {
    }

    public function setValidator(ValidatorInterface $validator): void
    {
    }
}

<?php

namespace JG\BatchEntityImportBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

interface ImportControllerInterface
{
    public function setTranslator(TranslatorInterface $translator): void;

    public function setEntityManager(EntityManagerInterface $em): void;

    public function setValidator(ValidatorInterface $validator): void;
}

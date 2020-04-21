<?php

namespace JG\BatchEntityImportBundle\Tests\Fixtures;

use Knp\DoctrineBehaviors\Contract\Entity\TranslationInterface;
use Knp\DoctrineBehaviors\Model\Translatable\TranslationTrait;

class TranslatableEntityTranslation implements TranslationInterface
{
    use TranslationTrait;

    public string    $testTranslationPublic;
    protected string $testTranslationProtected;
    private string   $testTranslationPrivate;
}

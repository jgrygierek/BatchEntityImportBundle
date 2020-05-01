<?php

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Entity;

use Knp\DoctrineBehaviors\Contract\Entity\TranslationInterface;
use Knp\DoctrineBehaviors\Model\Translatable\TranslationTrait;

class TranslatableEntityTranslation extends AbstractEntity implements TranslationInterface
{
    use TranslationTrait;

    public string    $testTranslationPublic;
    protected string $testTranslationProtected;
    private string   $testTranslationPrivate;
}

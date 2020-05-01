<?php

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Entity;

use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Knp\DoctrineBehaviors\Model\Translatable\TranslatableTrait;

class TranslatableEntity extends AbstractEntity implements TranslatableInterface
{
    use TranslatableTrait;

    public string    $testPropertyPublic;
    protected string $testPropertyProtected;
    private string   $testPropertyPrivate;
}

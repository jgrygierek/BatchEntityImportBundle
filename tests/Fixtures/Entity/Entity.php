<?php

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class Entity extends AbstractEntity
{
    public string    $testPropertyPublic;
    protected string $testPropertyProtected;
    private string   $testPropertyPrivate;
}

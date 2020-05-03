<?php

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class TestEntity extends AbstractEntity
{
    private string $testProperty = '';

    public function setTestProperty(string $testProperty): void
    {
        $this->testProperty = $testProperty;
    }

    public function getTestProperty(): string
    {
        return $this->testProperty;
    }
}

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class TestEntity extends AbstractEntity
{
    #[ORM\Column(type: 'string')]
    public string $testPublicProperty = '';
    #[ORM\Column(type: 'string', unique: true)]
    private string $testPrivateProperty = '';
    #[ORM\Column(type: 'string')]
    private string $testPrivatePropertyNoSetter = '';

    public function setTestPrivateProperty(string $testPrivateProperty): void
    {
        $this->testPrivateProperty = $testPrivateProperty;
    }

    public function getTestPrivateProperty(): string
    {
        return $this->testPrivateProperty;
    }
}

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Stringable;

#[ORM\Entity]
class TestEntity extends AbstractEntity implements Stringable
{
    #[ORM\Column(type: 'string')]
    public string $testPublicProperty = '';
    #[ORM\Column(type: 'string', unique: true)]
    private string $testPrivateProperty = '';
    #[ORM\Column(type: 'string')]
    private string $testPrivateProperty2 = '';
    #[ORM\Column(type: 'string')]
    private string $testPrivatePropertyNoSetter = '';
    #[ORM\Column(type: 'json', nullable: true)]
    private array $testArrayField = [];
    #[ORM\ManyToOne]
    private ?TestRelatedEntity $testRelatedEntity = null;
    #[ORM\ManyToOne]
    private ?TestRelatedEntity $testSecondRelatedEntity = null;

    public function setTestPrivateProperty(string $testPrivateProperty): void
    {
        $this->testPrivateProperty = $testPrivateProperty;
    }

    public function getTestPrivateProperty(): string
    {
        return $this->testPrivateProperty;
    }

    public function setTestPrivateProperty2(string $testPrivateProperty2): void
    {
        $this->testPrivateProperty2 = $testPrivateProperty2;
    }

    public function getTestPrivateProperty2(): string
    {
        return $this->testPrivateProperty2;
    }

    public function getTestArrayField(): array
    {
        return $this->testArrayField;
    }

    public function setTestArrayField(array $testArrayField): void
    {
        $this->testArrayField = $testArrayField;
    }

    public function getTestRelatedEntity(): ?TestRelatedEntity
    {
        return $this->testRelatedEntity;
    }

    public function setTestRelatedEntity(?TestRelatedEntity $testRelatedEntity): void
    {
        $this->testRelatedEntity = $testRelatedEntity;
    }

    public function getTestSecondRelatedEntity(): ?TestRelatedEntity
    {
        return $this->testSecondRelatedEntity;
    }

    public function setTestSecondRelatedEntity(?TestRelatedEntity $testSecondRelatedEntity): void
    {
        $this->testSecondRelatedEntity = $testSecondRelatedEntity;
    }

    public function __toString(): string
    {
        return (string) $this->getId();
    }
}

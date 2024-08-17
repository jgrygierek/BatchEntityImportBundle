<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\KnpLabs\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\AbstractEntity;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Knp\DoctrineBehaviors\Model\Translatable\TranslatableTrait;
use Stringable;

/**
 * @method string getTestTranslationProperty()
 */
#[ORM\Entity]
class TranslatableEntity extends AbstractEntity implements TranslatableInterface, Stringable
{
    use TranslatableTrait;

    #[ORM\Column(type: 'string')]
    public string $testPublicProperty = '';
    #[ORM\Column(type: 'string', unique: true)]
    private string $testPrivateProperty = '';
    #[ORM\Column(type: 'string')]
    private string $testPrivateProperty2 = '';
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

    public function setTestPrivateProperty2(string $testPrivateProperty2): void
    {
        $this->testPrivateProperty2 = $testPrivateProperty2;
    }

    public function getTestPrivateProperty2(): string
    {
        return $this->testPrivateProperty2;
    }

    public function __call(string $method, array $arguments)
    {
        return $this->proxyCurrentLocaleTranslation($method, $arguments);
    }

    public function __toString(): string
    {
        return (string) $this->translate();
    }
}

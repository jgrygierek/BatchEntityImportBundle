<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Knp\DoctrineBehaviors\Model\Translatable\TranslatableTrait;

/**
 * @method string getTestTranslationProperty()
 *
 * @ORM\Entity()
 */
class TranslatableEntity extends AbstractEntity implements TranslatableInterface
{
    use TranslatableTrait;

    /**
     * @ORM\Column(type="string")
     */
    public string $testPublicProperty = '';
    /**
     * @ORM\Column(type="string", unique=true)
     */
    private string $testPrivateProperty = '';
    /**
     * @ORM\Column(type="string")
     */
    private string $testPrivatePropertyNoSetter = '';

    public function setTestPrivateProperty(string $testPrivateProperty): void
    {
        $this->testPrivateProperty = $testPrivateProperty;
    }

    public function getTestPrivateProperty(): string
    {
        return $this->testPrivateProperty;
    }

    public function __call($method, $arguments)
    {
        return $this->proxyCurrentLocaleTranslation($method, $arguments);
    }

    public function __toString(): string
    {
        return $this->testPrivateProperty;
    }
}

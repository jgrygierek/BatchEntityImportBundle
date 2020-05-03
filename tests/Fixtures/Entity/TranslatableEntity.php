<?php

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

    private string $testProperty = '';

    public function setTestProperty(string $testProperty): void
    {
        $this->testProperty = $testProperty;
    }

    public function getTestProperty(): string
    {
        return $this->testProperty;
    }

    public function __call($method, $arguments)
    {
        return $this->proxyCurrentLocaleTranslation($method, $arguments);
    }
}

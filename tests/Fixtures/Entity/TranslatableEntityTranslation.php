<?php

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Contract\Entity\TranslationInterface;
use Knp\DoctrineBehaviors\Model\Translatable\TranslationTrait;

/**
 * @ORM\Entity()
 */
class TranslatableEntityTranslation extends AbstractEntity implements TranslationInterface
{
    use TranslationTrait;

    /**
     * @ORM\Column(type="string")
     */
    private string $testTranslationProperty = '';

    public function getTestTranslationProperty(): string
    {
        return $this->testTranslationProperty;
    }

    public function setTestTranslationProperty(string $testTranslationProperty): void
    {
        $this->testTranslationProperty = $testTranslationProperty;
    }
}

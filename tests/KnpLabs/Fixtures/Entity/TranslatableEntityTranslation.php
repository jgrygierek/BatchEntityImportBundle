<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\KnpLabs\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\AbstractEntity;
use Knp\DoctrineBehaviors\Contract\Entity\TranslationInterface;
use Knp\DoctrineBehaviors\Model\Translatable\TranslationTrait;
use Stringable;

#[ORM\Entity]
class TranslatableEntityTranslation extends AbstractEntity implements TranslationInterface, Stringable
{
    use TranslationTrait;

    #[ORM\Column(type: 'string')]
    private string $testTranslationProperty = '';

    public function __toString(): string
    {
        return $this->testTranslationProperty;
    }

    public function getTestTranslationProperty(): string
    {
        return $this->testTranslationProperty;
    }

    public function setTestTranslationProperty(string $testTranslationProperty): void
    {
        $this->testTranslationProperty = $testTranslationProperty;
    }
}

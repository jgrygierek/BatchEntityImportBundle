<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Data;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntityTranslation;

class TranslatableEntityFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 0; $i < 20; ++$i) {
            $entity = new TranslatableEntity();
            $entity->setTestPrivateProperty('abcd_' . $i);

            if ($i < 15) {
                /** @var TranslatableEntityTranslation $translatedEntity */
                $translatedEntity = $entity->translate('en');
                $translatedEntity->setTestTranslationProperty('qwerty_en_' . $i);
            }

            if ($i > 4) {
                $translatedEntity = $entity->translate('pl');
                $translatedEntity->setTestTranslationProperty('qwerty_pl_' . $i);
            }

            $manager->persist($entity);
            $entity->mergeNewTranslations();
        }

        $manager->flush();
    }
}

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Data;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;

class TestEntityFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 0; $i < 20; ++$i) {
            $entity = new TestEntity();
            $entity->setTestPrivateProperty('abcd_' . $i);

            $manager->persist($entity);
        }

        $manager->flush();
    }
}

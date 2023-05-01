<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use JG\BatchEntityImportBundle\Tests\Fixtures\Data\TranslatableEntityFixtures;

class DatabaseLoader
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager, Connection $connection)
    {
        $this->entityManager = $entityManager;

        $connection->getConfiguration()->setSQLLogger(null);
    }

    public function reload(): void
    {
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        $entityClasses = [];
        foreach ($allMetadata as $classMetadata) {
            $entityClasses[] = $classMetadata->getName();
        }

        $this->reloadEntityClasses($entityClasses);
    }

    public function loadFixtures(): void
    {
        $loader = new Loader();
        $loader->addFixture(new TranslatableEntityFixtures());

        $purger = new ORMPurger();
        $executor = new ORMExecutor($this->entityManager, $purger);
        $executor->execute($loader->getFixtures());
    }

    private function reloadEntityClasses(array $entityClasses): void
    {
        $schema = [];
        foreach ($entityClasses as $entityClass) {
            $schema[] = $this->entityManager->getClassMetadata($entityClass);
        }

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($schema);
        $schemaTool->createSchema($schema);
    }
}

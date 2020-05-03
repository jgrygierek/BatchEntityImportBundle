<?php

namespace JG\BatchEntityImportBundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symplify\PackageBuilder\Tests\AbstractKernelTestCase;

abstract class AbstractDatabaseTestCase extends AbstractKernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->bootKernel(TestKernel::class);
        $this->entityManager = static::$container->get('doctrine.orm.entity_manager');
        $this->loadDatabaseFixtures();
    }

    protected function loadDatabaseFixtures(): void
    {
        $databaseLoader = static::$container->get(DatabaseLoader::class);
        $databaseLoader->reload();
    }
}

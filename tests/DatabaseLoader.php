<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use JG\BatchEntityImportBundle\Tests\Fixtures\Data\TestEntityFixtures;
use JG\BatchEntityImportBundle\Tests\Fixtures\Data\TranslatableEntityFixtures;

class DatabaseLoader
{
    use SkippedTestsTrait;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
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
        $loader->addFixture(new TestEntityFixtures());
        if ($this->isKnpLabsDoctrineBehaviorsInstalled()) {
            $loader->addFixture(new TranslatableEntityFixtures());
        }

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

        $this->enableSchemaEditorApi();

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($schema);
        $schemaTool->createSchema($schema);
    }

    /**
     * Without it SchemaTool triggers deprecation since ORM 3.8 (schema editor API will be required in ORM 4).
     * API is available since ORM 3.8 and DBAL 4.5, older versions are still tested with the lowest dependencies.
     */
    private function enableSchemaEditorApi(): void
    {
        // results depend on installed versions of doctrine/orm and doctrine/dbal
        $isAvailable = \method_exists(Configuration::class, 'setUseSchemaEditorApi') // @phpstan-ignore function.alreadyNarrowedType
            && \method_exists(Schema::class, 'edit'); // @phpstan-ignore function.alreadyNarrowedType

        if ($isAvailable) {
            $this->entityManager->getConfiguration()->setUseSchemaEditorApi(true);
        }
    }
}

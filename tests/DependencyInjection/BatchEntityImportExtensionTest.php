<?php

namespace JG\BatchEntityImportBundle\Tests\DependencyInjection;

use JG\BatchEntityImportBundle\Controller\ImportControllerInterface;
use JG\BatchEntityImportBundle\DependencyInjection\BatchEntityImportExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

class BatchEntityImportExtensionTest extends AbstractExtensionTestCase
{
    public function testInstanceHasTag(): void
    {
        $this->load();

        $autoConfiguredInstances = $this->container->getAutoconfiguredInstanceof();
        $this->assertArrayHasKey(ImportControllerInterface::class, $autoConfiguredInstances);

        $instance = $autoConfiguredInstances[ImportControllerInterface::class];
        $this->assertNotEmpty($instance->getTag('batch_entity_import.controller'));
    }

    protected function getContainerExtensions(): array
    {
        return [new BatchEntityImportExtension()];
    }
}

<?php

namespace JG\BatchEntityImportBundle\Tests\DependencyInjection\Compiler;

use JG\BatchEntityImportBundle\DependencyInjection\Compiler\AutoConfigureCompilerPass;
use JG\BatchEntityImportBundle\Tests\Fixtures\Controller\Controller;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AutoConfigureCompilerPassTest extends AbstractCompilerPassTestCase
{
    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AutoConfigureCompilerPass());
    }

    public function testControllerMethodCalls(): void
    {
        $controllerDefinition = new Definition(Controller::class);
        $controllerDefinition->addTag('batch_entity_import.controller');
        $this->setDefinition('controller', $controllerDefinition);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            'controller',
            'setTranslator',
            [new Reference('translator')]
        );

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            'controller',
            'setEntityManager',
            [new Reference('doctrine.orm.entity_manager')]
        );

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            'controller',
            'setValidator',
            [new Reference('validator')]
        );
    }
}

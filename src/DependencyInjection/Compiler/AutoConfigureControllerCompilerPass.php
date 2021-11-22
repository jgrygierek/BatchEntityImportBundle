<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AutoConfigureControllerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $configurations = [];

        $taggedConfigurations = $container->findTaggedServiceIds('batch_entity_import.configuration');
        foreach ($taggedConfigurations as $id => $tags) {
            $configurations[$id] = $container->getDefinition($id);
        }

        $taggedControllers = $container->findTaggedServiceIds('batch_entity_import.controller');
        foreach ($taggedControllers as $id => $tags) {
            $controllerDefinition = $container->getDefinition($id);
            $controllerDefinition->addMethodCall('setImportConfiguration', [$configurations]);
        }
    }
}

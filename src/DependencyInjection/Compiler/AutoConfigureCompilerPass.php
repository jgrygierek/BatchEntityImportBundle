<?php

namespace JG\BatchEntityImportBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AutoConfigureCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $taggedServices = $container->findTaggedServiceIds('batch_entity_import.controller');

        foreach ($taggedServices as $id => $tags) {
            $container
                ->getDefinition($id)
                ->addMethodCall('setTranslator', [new Reference('translator')])
                ->addMethodCall('setEntityManager', [new Reference('doctrine.orm.entity_manager')]);
        }
    }
}

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('batch_entity_import');

        $nodeBuilder = $treeBuilder->getRootNode()->children();
        $this->addTemplatesConfig($nodeBuilder);

        return $treeBuilder;
    }

    private function addTemplatesConfig(NodeBuilder $parentBuilder): void
    {
        $builder = $parentBuilder->arrayNode('templates')->addDefaultsIfNotSet()->children();

        $this->addNodeConfig($builder, 'select_file', '@BatchEntityImport/select_file.html.twig');
        $this->addNodeConfig($builder, 'edit_matrix', '@BatchEntityImport/edit_matrix.html.twig');
        $this->addNodeConfig($builder, 'layout', '@BatchEntityImport/layout.html.twig');
    }

    private function addNodeConfig(NodeBuilder $builder, string $name, string $value): void
    {
        $builder
            ->scalarNode($name)
            ->defaultValue($value)
            ->cannotBeEmpty()
            ->end();
    }
}

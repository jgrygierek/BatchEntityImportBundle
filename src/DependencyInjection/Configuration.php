<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\DependencyInjection;

use JG\BatchEntityImportBundle\Utils\EncodingHelper;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('batch_entity_import');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $this->addTemplatesConfig($rootNode->children());
        $rootNode->children()
            ->scalarNode('csv_fallback_encoding')
            ->info('Encoding used for CSV files which are not encoded in Unicode, e.g. "CP1250". Without it such files are rejected.')
            ->defaultNull()
            ->validate()
            // checked already when container is built, so typo in name of encoding is found as early as possible
            ->ifTrue(static fn (mixed $encoding): bool => null !== $encoding && (!\is_string($encoding) || !EncodingHelper::isSupported($encoding)))
            ->thenInvalid('Encoding %s is not supported.')
            ->end()
            ->end();

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

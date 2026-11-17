<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle;

use JG\BatchEntityImportBundle\Form\Type\ArrayTextType;
use JG\BatchEntityImportBundle\Form\Type\EntityLookupTextType;
use JG\BatchEntityImportBundle\Form\Type\EntityLookupType;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixFactory;
use JG\BatchEntityImportBundle\Service\CsvDelimiterDetector;
use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use JG\BatchEntityImportBundle\Twig\TemplateExtension;
use JG\BatchEntityImportBundle\Utils\EncodingHelper;
use JG\BatchEntityImportBundle\Validator\Constraints\DatabaseEntityUniqueValidator;
use JG\BatchEntityImportBundle\Validator\Constraints\RelatedEntityExistsValidator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class BatchEntityImportBundle extends AbstractBundle
{
    private const array DEFAULT_TEMPLATES = [
        'select_file' => '@BatchEntityImport/select_file.html.twig',
        'edit_matrix' => '@BatchEntityImport/edit_matrix.html.twig',
        'layout' => '@BatchEntityImport/layout.html.twig',
    ];

    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();

        $templates = $children->arrayNode('templates')->addDefaultsIfNotSet()->children();
        foreach (self::DEFAULT_TEMPLATES as $name => $template) {
            $templates->scalarNode($name)->defaultValue($template)->cannotBeEmpty();
        }

        $children
            ->scalarNode('csv_fallback_encoding')
            ->info('Encoding used for CSV files which are not encoded in Unicode, e.g. "CP1250". Without it such files are rejected.')
            ->defaultNull()
            ->validate()
            // checked already when container is built, so typo in name of encoding is found as early as possible
            ->ifTrue(static fn (mixed $encoding): bool => null !== $encoding && (!\is_string($encoding) || !EncodingHelper::isSupported($encoding)))
            ->thenInvalid('Encoding %s is not supported.');
    }

    /**
     * @param array{templates: array<string, string>, csv_fallback_encoding: string|null} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $parameters = $container->parameters();
        $parameters->set('batch_entity_import.csv_fallback_encoding', $config['csv_fallback_encoding']);
        $parameters->set('batch_entity_import.templates', $config['templates']);
        foreach ($config['templates'] as $name => $template) {
            $parameters->set('batch_entity_import.templates.' . $name, $template);
        }

        $services = $container->services();

        $services->set(TemplateExtension::class)
            ->public()
            ->args([param('batch_entity_import.templates')])
            ->tag('twig.attribute_extension')
            ->tag('twig.runtime');

        $services->set(CsvDelimiterDetector::class);

        $services->set(MatrixFactory::class)
            ->args([service(CsvDelimiterDetector::class)]);

        $services->set(RelatedEntityFinder::class)
            ->args([service('doctrine')])
            ->tag('kernel.reset', ['method' => 'reset']);

        $services->set(ArrayTextType::class)
            ->arg('$translator', service('translator'))
            ->tag('form.type');

        $services->set(EntityLookupType::class)
            ->arg('$relatedEntityFinder', service(RelatedEntityFinder::class))
            ->tag('form.type')
            ->tag('kernel.reset', ['method' => 'reset']);

        $services->set(EntityLookupTextType::class)
            ->arg('$relatedEntityFinder', service(RelatedEntityFinder::class))
            ->tag('form.type');

        $services->set(DatabaseEntityUniqueValidator::class)
            ->args([service('doctrine')])
            ->tag('validator.constraint_validator');

        $services->set(RelatedEntityExistsValidator::class)
            ->args([service(RelatedEntityFinder::class)])
            ->tag('validator.constraint_validator');
    }
}

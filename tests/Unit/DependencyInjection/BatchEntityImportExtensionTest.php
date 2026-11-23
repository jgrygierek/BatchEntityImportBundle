<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\DependencyInjection;

use Generator;
use JG\BatchEntityImportBundle\BatchEntityImportBundle;
use JG\BatchEntityImportBundle\Form\Type\MatrixRecordType;
use JG\BatchEntityImportBundle\Import\EntityImporter;
use JG\BatchEntityImportBundle\Import\ImportHandler;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixFactory;
use JG\BatchEntityImportBundle\Service\CsvDelimiterDetector;
use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Controller\ControllerWithAttribute;
use JG\BatchEntityImportBundle\Validator\Constraints\DatabaseEntityUniqueValidator;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class BatchEntityImportExtensionTest extends AbstractExtensionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setKernelParameters($this->container);
        $this->load();
    }

    public function testParameters(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('batch_entity_import.templates.select_file', '@BatchEntityImport/select_file.html.twig');
        $this->assertContainerBuilderHasParameter('batch_entity_import.templates.edit_matrix', '@BatchEntityImport/edit_matrix.html.twig');
        $this->assertContainerBuilderHasParameter('batch_entity_import.templates.layout', '@BatchEntityImport/layout.html.twig');
    }

    public function testCsvFallbackEncodingIsDisabledByDefault(): void
    {
        $this->assertContainerBuilderHasParameter('batch_entity_import.csv_fallback_encoding', null);
    }

    public function testCsvFallbackEncoding(): void
    {
        $this->load(['csv_fallback_encoding' => 'CP1250']);

        $this->assertContainerBuilderHasParameter('batch_entity_import.csv_fallback_encoding', 'CP1250');
    }

    #[DataProvider('invalidEncodingProvider')]
    public function testInvalidCsvFallbackEncoding(mixed $encoding): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load(['csv_fallback_encoding' => $encoding]);
    }

    public static function invalidEncodingProvider(): Generator
    {
        yield 'unknown encoding' => ['CP125O'];
        yield 'empty string' => [''];
        yield 'not a string' => [1250];
    }

    public function testControllerWithAttributeGetsConfigurationAndHandler(): void
    {
        $this->container->register(BaseConfiguration::class, BaseConfiguration::class);
        $this->container->register(ControllerWithAttribute::class, ControllerWithAttribute::class)->setAutoconfigured(true);
        $this->container->compile(true);

        $calls = $this->container->getDefinition(ControllerWithAttribute::class)->getMethodCalls();
        self::assertEquals([
            ['setImportConfiguration', [new Reference(BaseConfiguration::class)]],
            ['setImportHandler', [new Reference(ImportHandler::class)]],
        ], $calls);
    }

    public function testMatrixFactoryIsService(): void
    {
        $this->assertContainerBuilderHasService(MatrixFactory::class);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(MatrixFactory::class, 0, new Reference(CsvDelimiterDetector::class));
    }

    public function testMatrixRecordTypeIsService(): void
    {
        $this->assertContainerBuilderHasServiceDefinitionWithTag(MatrixRecordType::class, 'form.type');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(MatrixRecordType::class, 0, new Reference(RelatedEntityFinder::class));
    }

    public function testImportHandlerIsService(): void
    {
        $this->assertContainerBuilderHasService(ImportHandler::class);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ImportHandler::class, 0, new Reference('form.factory'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ImportHandler::class, 1, new Reference('validator'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ImportHandler::class, 2, new Reference('translator'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ImportHandler::class, 3, new Reference(MatrixFactory::class));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ImportHandler::class, 4, new Reference(EntityImporter::class));
    }

    public function testEntityImporterIsService(): void
    {
        $this->assertContainerBuilderHasService(EntityImporter::class);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(EntityImporter::class, 0, new Reference('doctrine'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(EntityImporter::class, 1, new Reference('event_dispatcher'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(EntityImporter::class, 2, new Reference(RelatedEntityFinder::class));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(EntityImporter::class, 3, new Reference('form.registry'));
    }

    public function testDatabaseEntityUniqueValidatorUsesManagerRegistry(): void
    {
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(DatabaseEntityUniqueValidator::class, 0, new Reference('doctrine'));
    }

    public function testConfigurationFromAttributeIsNotServiceException(): void
    {
        // new container is used, because the one from test case has disabled passes which check references
        $container = new ContainerBuilder();
        $this->setKernelParameters($container);
        new BatchEntityImportBundle()->getContainerExtension()?->load([], $container);
        // services of FrameworkBundle used by services of bundle
        foreach (['form.factory', 'form.registry', 'validator', 'translator', 'doctrine', 'event_dispatcher'] as $id) {
            $container->register($id)->setSynthetic(true);
        }
        $container->register(ControllerWithAttribute::class, ControllerWithAttribute::class)->setAutoconfigured(true)->setPublic(true);

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage(\sprintf('The service "%s" has a dependency on a non-existent service "%s".', ControllerWithAttribute::class, BaseConfiguration::class));
        $container->compile(true);
    }

    /**
     * Parameters which are always set by kernel, extension of bundle requires them on Symfony 7.4.
     */
    private function setKernelParameters(ContainerBuilder $container): void
    {
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
    }

    protected function getContainerExtensions(): array
    {
        return [new BatchEntityImportBundle()->getContainerExtension()];
    }
}

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\DependencyInjection;

use Generator;
use JG\BatchEntityImportBundle\DependencyInjection\BatchEntityImportExtension;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Controller\ControllerWithAttribute;
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

    public function testControllerWithAttributeGetsConfiguration(): void
    {
        $this->container->register(BaseConfiguration::class, BaseConfiguration::class);
        $this->container->register(ControllerWithAttribute::class, ControllerWithAttribute::class)->setAutoconfigured(true);
        $this->container->compile();

        $calls = $this->container->getDefinition(ControllerWithAttribute::class)->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('setImportConfiguration', $calls[0][0]);
        self::assertEquals([new Reference(BaseConfiguration::class)], $calls[0][1]);
    }

    public function testConfigurationFromAttributeIsNotServiceException(): void
    {
        // new container is used, because the one from test case has disabled passes which check references
        $container = new ContainerBuilder();
        new BatchEntityImportExtension()->load([], $container);
        $container->register(ControllerWithAttribute::class, ControllerWithAttribute::class)->setAutoconfigured(true)->setPublic(true);

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage(\sprintf('The service "%s" has a dependency on a non-existent service "%s".', ControllerWithAttribute::class, BaseConfiguration::class));
        $container->compile();
    }

    protected function getContainerExtensions(): array
    {
        return [new BatchEntityImportExtension()];
    }
}

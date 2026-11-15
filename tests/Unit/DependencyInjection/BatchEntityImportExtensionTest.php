<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\DependencyInjection;

use Generator;
use JG\BatchEntityImportBundle\Controller\ImportConfigurationAutoInjectInterface;
use JG\BatchEntityImportBundle\DependencyInjection\BatchEntityImportExtension;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

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

    public function testImportConfigurationHasTag(): void
    {
        $autoConfiguredInstances = $this->container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(ImportConfigurationInterface::class, $autoConfiguredInstances);

        $instance = $autoConfiguredInstances[ImportConfigurationInterface::class];
        self::assertTrue($instance->hasTag('batch_entity_import.configuration'));
    }

    public function testControllerHasTag(): void
    {
        $autoConfiguredInstances = $this->container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(ImportConfigurationAutoInjectInterface::class, $autoConfiguredInstances);

        $instance = $autoConfiguredInstances[ImportConfigurationAutoInjectInterface::class];
        self::assertTrue($instance->hasTag('batch_entity_import.controller'));
    }

    protected function getContainerExtensions(): array
    {
        return [new BatchEntityImportExtension()];
    }
}

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\DependencyInjection;

use JG\BatchEntityImportBundle\DependencyInjection\BatchEntityImportExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

class BatchEntityImportExtensionTest extends AbstractExtensionTestCase
{
    public function testParameters(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('batch_entity_import.templates.select_file', '@BatchEntityImport/select_file.html.twig');
        $this->assertContainerBuilderHasParameter('batch_entity_import.templates.edit_matrix', '@BatchEntityImport/edit_matrix.html.twig');
        $this->assertContainerBuilderHasParameter('batch_entity_import.templates.layout', '@BatchEntityImport/layout.html.twig');
    }

    protected function getContainerExtensions(): array
    {
        return [new BatchEntityImportExtension()];
    }
}

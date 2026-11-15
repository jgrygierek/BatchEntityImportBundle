<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Controller;

use JG\BatchEntityImportBundle\Controller\ImportControllerTrait;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ImportControllerTraitTest extends TestCase
{
    public function testTemplateNamesAreTakenFromParameters(): void
    {
        $controller = $this->createController([
            'batch_entity_import.templates.select_file' => 'select_file.html.twig',
            'batch_entity_import.templates.edit_matrix' => 'edit_matrix.html.twig',
        ]);

        self::assertSame('select_file.html.twig', $controller->selectFileTemplateName());
        self::assertSame('edit_matrix.html.twig', $controller->matrixEditTemplateName());
    }

    public function testNotConfiguredTemplateException(): void
    {
        $controller = $this->createController(['batch_entity_import.templates.select_file' => null]);

        $this->expectExceptionObject(new LogicException('Template "select_file" is not configured.'));
        $controller->selectFileTemplateName();
    }

    public function testConfigurationSetByContainerIsUsed(): void
    {
        $configuration = $this->createStub(ImportConfigurationInterface::class);
        $controller = $this->createController([]);
        $controller->setImportConfiguration($configuration);

        self::assertSame($configuration, $controller->importConfiguration());
    }

    public function testMissingConfigurationException(): void
    {
        $controller = $this->createController([]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Import configuration is not set. Add #[AsImportController] attribute to');
        $controller->importConfiguration();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createController(array $parameters): object
    {
        return new class($parameters) {
            use ImportControllerTrait;

            /**
             * @param array<string, mixed> $parameters
             */
            public function __construct(private readonly array $parameters)
            {
            }

            public function importConfiguration(): ImportConfigurationInterface
            {
                return $this->getImportConfiguration();
            }

            public function selectFileTemplateName(): string
            {
                return $this->getSelectFileTemplateName();
            }

            public function matrixEditTemplateName(): string
            {
                return $this->getMatrixEditTemplateName();
            }

            protected function getParameter(string $name): mixed
            {
                return $this->parameters[$name] ?? null;
            }

            protected function getMatrixSaveActionUrl(): string
            {
                return '';
            }

            protected function redirectToImport(): RedirectResponse
            {
                return new RedirectResponse('/');
            }
        };
    }
}

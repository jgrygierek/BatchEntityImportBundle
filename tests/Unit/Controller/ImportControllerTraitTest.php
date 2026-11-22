<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Controller;

use Doctrine\Persistence\ManagerRegistry;
use JG\BatchEntityImportBundle\Controller\ImportControllerTrait;
use JG\BatchEntityImportBundle\Import\EntityImporter;
use JG\BatchEntityImportBundle\Import\ImportHandler;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixFactory;
use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    public function testHandlerSetByContainerIsUsed(): void
    {
        $handler = new ImportHandler(
            $this->createStub(FormFactoryInterface::class),
            $this->createStub(ValidatorInterface::class),
            $this->createStub(TranslatorInterface::class),
            new MatrixFactory(),
            new EntityImporter($this->createStub(ManagerRegistry::class), $this->createStub(EventDispatcherInterface::class), $this->createStub(RelatedEntityFinder::class)),
        );
        $controller = $this->createController([]);
        $controller->setImportHandler($handler);

        self::assertSame($handler, $controller->importHandler());
    }

    public function testMissingHandlerException(): void
    {
        $controller = $this->createController([]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Import handler is not set. Add #[AsImportController] attribute to');
        $controller->importHandler();
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

            public function importHandler(): ImportHandler
            {
                return $this->getImportHandler();
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

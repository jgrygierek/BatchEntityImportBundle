<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Generator;
use JG\BatchEntityImportBundle\Controller\ImportControllerTrait;
use JG\BatchEntityImportBundle\Import\EntityImporter;
use JG\BatchEntityImportBundle\Import\ImportHandler;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixFactory;
use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRegistryInterface;
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

    #[DataProvider('csvFallbackEncodingProvider')]
    public function testCsvFallbackEncodingIsTakenFromParameter(mixed $parameter, ?string $expected): void
    {
        $controller = $this->createController(['batch_entity_import.csv_fallback_encoding' => $parameter]);

        self::assertSame($expected, $controller->csvFallbackEncoding());
    }

    public static function csvFallbackEncodingProvider(): Generator
    {
        yield 'encoding' => ['CP1250', 'CP1250'];
        yield 'not set' => [null, null];
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
            new EntityImporter(
                $this->createStub(ManagerRegistry::class),
                $this->createStub(EventDispatcherInterface::class),
                $this->createStub(RelatedEntityFinder::class),
                $this->createStub(FormRegistryInterface::class),
            ),
        );
        $controller = $this->createController([]);
        $controller->setImportHandler($handler);

        self::assertSame($handler, $controller->importHandler());
    }

    public function testMissingHandlerException(): void
    {
        $controller = $this->createController([]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Import handler is not set. Add #[AsImportController] attribute to');
        $controller->importHandler();
    }

    public function testMissingConfigurationException(): void
    {
        $controller = $this->createController([]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Import configuration is not set. Add #[AsImportController] attribute to');
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

            public function csvFallbackEncoding(): ?string
            {
                return $this->getCsvFallbackEncoding();
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

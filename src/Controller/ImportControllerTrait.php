<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Controller;

use JG\BatchEntityImportBundle\Import\ImportHandler;
use JG\BatchEntityImportBundle\Import\ImportResult;
use JG\BatchEntityImportBundle\Import\ImportStep;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use LogicException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connects ImportHandler with controller which extends AbstractController: shows views, redirects and adds flash messages.
 */
trait ImportControllerTrait
{
    protected ?ImportConfigurationInterface $importConfiguration = null;
    protected ?ImportHandler $importHandler = null;

    /**
     * Called by container for controller with #[AsImportController] attribute.
     */
    public function setImportConfiguration(ImportConfigurationInterface $importConfiguration): void
    {
        $this->importConfiguration = $importConfiguration;
    }

    /**
     * Called by container for controller with #[AsImportController] attribute.
     */
    public function setImportHandler(ImportHandler $importHandler): void
    {
        $this->importHandler = $importHandler;
    }

    abstract protected function redirectToImport(): RedirectResponse;

    abstract protected function getMatrixSaveActionUrl(): string;

    /**
     * @throws LogicException
     */
    protected function doImport(Request $request): Response
    {
        $configuration = $this->getImportConfiguration();
        $result = $this->getImportHandler()->handleFile($request, $configuration, $this->getMatrixSaveActionUrl(), $this->getCsvFallbackEncoding());

        return $this->createImportResponse($result);
    }

    /**
     * @throws LogicException
     */
    protected function doImportSave(Request $request): Response
    {
        $configuration = $this->getImportConfiguration();
        $result = $this->getImportHandler()->handleSave($request, $configuration, $this->getMatrixSaveActionUrl());

        return $this->createImportResponse($result);
    }

    protected function prepareSelectFileView(FormInterface $form): Response
    {
        return $this->prepareView(
            $this->getSelectFileTemplateName(),
            [
                'form' => $form->createView(),
            ]
        );
    }

    protected function prepareMatrixEditView(FormInterface $form, Matrix $matrix): Response
    {
        $configuration = $this->getImportConfiguration();

        return $this->prepareView(
            $this->getMatrixEditTemplateName(),
            [
                'header_info' => $matrix->getHeaderInfo($configuration->getEntityClassName(), $configuration->getAllowedColumns()),
                'form' => $form->createView(),
                'importConfiguration' => $configuration,
            ]
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function prepareView(string $view, array $parameters = []): Response
    {
        return $this->render($view, $parameters);
    }

    protected function getSelectFileTemplateName(): string
    {
        return $this->getTemplateNameFromConfiguration('select_file');
    }

    protected function getMatrixEditTemplateName(): string
    {
        return $this->getTemplateNameFromConfiguration('edit_matrix');
    }

    /**
     * Encoding used for CSV file which is not encoded in Unicode, e.g. "CP1250" for Central European languages.
     * Without it such file is rejected, because its encoding can't be recognized reliably.
     * By default value from bundle configuration is used, override this method to use other encoding in single controller.
     */
    protected function getCsvFallbackEncoding(): ?string
    {
        $encoding = $this->getParameter('batch_entity_import.csv_fallback_encoding');

        return \is_string($encoding) ? $encoding : null;
    }

    /**
     * @throws LogicException
     */
    protected function getImportConfiguration(): ImportConfigurationInterface
    {
        return $this->importConfiguration ?? throw new LogicException($this->getNotConfiguredMessage('Import configuration'));
    }

    /**
     * @throws LogicException
     */
    protected function getImportHandler(): ImportHandler
    {
        return $this->importHandler ?? throw new LogicException($this->getNotConfiguredMessage('Import handler'));
    }

    private function createImportResponse(ImportResult $result): Response
    {
        foreach ($result->getMessages() as $message) {
            $this->addFlash($message->type, $message->message);
        }

        return match ($result->getStep()) {
            ImportStep::SELECT_FILE => $this->prepareSelectFileView($result->getForm()),
            ImportStep::EDIT_MATRIX => $this->prepareMatrixEditView($result->getForm(), $result->getMatrix()),
            ImportStep::FINISHED => $this->redirectToImport(),
        };
    }

    private function getNotConfiguredMessage(string $name): string
    {
        return \sprintf('%s is not set. Add #[AsImportController] attribute to "%s" and make sure that it is registered as autoconfigured service.', $name, static::class);
    }

    /**
     * @throws LogicException
     */
    private function getTemplateNameFromConfiguration(string $name): string
    {
        $template = $this->getParameter('batch_entity_import.templates.' . $name);
        if (!\is_string($template)) {
            throw new LogicException(\sprintf('Template "%s" is not configured.', $name));
        }

        return $template;
    }
}

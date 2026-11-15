<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Controller;

use InvalidArgumentException;
use JG\BatchEntityImportBundle\Exception\BatchEntityImportExceptionInterface;
use JG\BatchEntityImportBundle\Exception\FileEncodingException;
use JG\BatchEntityImportBundle\Exception\FileReadException;
use JG\BatchEntityImportBundle\Form\Type\FileImportType;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\FileImport;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixFactory;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Traversable;

trait BaseImportControllerTrait
{
    protected ?ImportConfigurationInterface $importConfiguration = null;

    abstract protected function getImportConfigurationClassName(): string;

    abstract protected function redirectToImport(): RedirectResponse;

    abstract protected function getSelectFileTemplateName(): string;

    abstract protected function getMatrixEditTemplateName(): string;

    abstract protected function prepareView(string $view, array $parameters = []): Response;

    abstract protected function createMatrixForm(Matrix $matrix): FormInterface;

    /**
     * @throws InvalidArgumentException
     * @throws \LogicException
     */
    protected function doImport(Request $request, ValidatorInterface $validator): Response
    {
        $fileImport = new FileImport($this->getImportConfiguration()->getAllowedFileExtensions());

        $form = $this->createForm(FileImportType::class, $fileImport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $matrix = null;
            $fileError = 'validation.file.unreadable';
            try {
                $matrix = MatrixFactory::createFromUploadedFile($fileImport->getFile(), $this->getCsvFallbackEncoding());
            } catch (FileEncodingException) {
                $fileError = 'validation.file.encoding';
            } catch (FileReadException) {
            }

            // validator is used also for unreadable file, so its message is translated the same way as other errors
            $errors = $matrix instanceof Matrix
                ? $validator->validate($matrix)
                : $validator->validate(false, new IsTrue(message: $fileError));

            if ($matrix instanceof Matrix && 0 === $errors->count()) {
                $form = $this->createMatrixForm($matrix);

                return $this->prepareMatrixEditView($form, $matrix, true);
            }
        } else {
            $errors = $form->getErrors();
        }

        $this->setErrorAsFlash($errors);

        return $this->prepareSelectFileView($form);
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

    protected function prepareMatrixEditView(FormInterface $form, Matrix $matrix, bool $manualSubmit = false): Response
    {
        if ($manualSubmit) {
            $this->manualSubmitMatrixForm($form, $matrix);
        }

        $configuration = $this->getImportConfiguration();

        return $this->prepareView(
            $this->getMatrixEditTemplateName(),
            [
                'header_info' => $matrix->getHeaderInfo($configuration->getEntityClassName()),
                'form' => $form->createView(),
                'importConfiguration' => $configuration,
            ]
        );
    }

    /**
     * @throws LogicException
     */
    protected function doImportSave(Request $request, TranslatorInterface $translator): Response
    {
        if ($this->isRequestDataTruncated($request)) {
            $msg = $translator->trans('error.data.truncated', ['%limit%' => $this->getMaxInputVars()], 'BatchEntityImportBundle');
            $this->addFlash('error', $msg);

            return $this->redirectToImport();
        }

        $matrixData = $request->request->all()['matrix'] ?? null;
        $records = \is_array($matrixData) ? ($matrixData['records'] ?? null) : null;
        $matrix = \is_array($records) ? MatrixFactory::createFromPostData($records) : new Matrix();

        // also when data sent in request has invalid structure
        if (!$matrix->getRecords()) {
            $msg = $translator->trans('error.data.not_found', [], 'BatchEntityImportBundle');
            $this->addFlash('error', $msg);

            return $this->redirectToImport();
        }

        $form = $this->createMatrixForm($matrix);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->getImportConfiguration()->import($matrix);
                $msg = $translator->trans('success.import', [], 'BatchEntityImportBundle');
                $this->addFlash('success', $msg);

                return $this->redirectToImport();
            } catch (BatchEntityImportExceptionInterface $e) {
                $msg = $translator->trans($e->getMessage(), [], 'BatchEntityImportBundle');
                $this->addFlash('error', $msg);
            }
        }

        $this->setErrorAsFlash($form->getErrors());

        return $this->prepareMatrixEditView($form, $matrix);
    }

    /**
     * PHP silently drops all fields above "max_input_vars" limit, so part of records would be imported
     * and the rest would be lost without any error. When number of received fields reaches the limit,
     * it is not possible to tell if something was dropped, so such request is rejected.
     */
    protected function isRequestDataTruncated(Request $request): bool
    {
        $fieldsNumber = 0;
        $data = $request->request->all();
        array_walk_recursive($data, static function () use (&$fieldsNumber): void {
            ++$fieldsNumber;
        });

        // limit can't be disabled in PHP, value lower than 1 means that it could not be read
        $limit = $this->getMaxInputVars();

        return $limit > 0 && $fieldsNumber >= $limit;
    }

    protected function getMaxInputVars(): int
    {
        return (int) \ini_get('max_input_vars');
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

    protected function getImportConfiguration(): ImportConfigurationInterface
    {
        if (!$this->importConfiguration) {
            throw new ServiceNotFoundException($this->getImportConfigurationClassName());
        }

        return $this->importConfiguration;
    }

    protected function setErrorAsFlash(Traversable $violations): void
    {
        $errors = iterator_to_array($violations);
        if ($errors) {
            $error = reset($errors);
            $this->addFlash('error', $error->getMessage());
        }
    }

    protected function manualSubmitMatrixForm(FormInterface $form, Matrix $matrix): void
    {
        $data = ['records' => array_map(static fn (MatrixRecord $record): array => $record->getData(), $matrix->getRecords())];

        $csrfTokenManager = $form->getConfig()->getOption('csrf_token_manager');
        if ($csrfTokenManager) {
            $tokenId = $form->getConfig()->getOption('csrf_token_id') ?? $form->getName();
            $data['_token'] = $csrfTokenManager->getToken($tokenId)->getValue();
        }

        $form->submit($data);
    }
}

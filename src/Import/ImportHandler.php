<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Import;

use InvalidArgumentException;
use JG\BatchEntityImportBundle\Exception\BatchEntityImportExceptionInterface;
use JG\BatchEntityImportBundle\Exception\FileEncodingException;
use JG\BatchEntityImportBundle\Exception\FileReadException;
use JG\BatchEntityImportBundle\Exception\MatrixException;
use JG\BatchEntityImportBundle\Form\Type\FileImportType;
use JG\BatchEntityImportBundle\Form\Type\MatrixType;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\FileImport;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixFactory;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Traversable;

/**
 * Logic of import, independent of controller. Configuration is passed to each method, so one handler is used for all imports.
 *
 * @internal used by ImportControllerTrait, it is not covered by backward compatibility promise yet
 */
final readonly class ImportHandler
{
    private const string TRANSLATION_DOMAIN = 'BatchEntityImportBundle';

    public function __construct(
        private FormFactoryInterface $formFactory,
        private ValidatorInterface $validator,
        private TranslatorInterface $translator,
        private MatrixFactory $matrixFactory,
        private EntityImporter $entityImporter,
    ) {
    }

    /**
     * Handles form with file. When file is valid, its data is returned as matrix to preview and edit.
     *
     * @param string $saveActionUrl URL to which data from preview is sent, it is handled by handleSave()
     * @param string|null $csvFallbackEncoding encoding used for CSV file which is not encoded in Unicode, without it such file is rejected
     *
     * @throws InvalidArgumentException when file extension or fallback encoding is not supported
     */
    public function handleFile(Request $request, ImportConfigurationInterface $configuration, string $saveActionUrl, ?string $csvFallbackEncoding = null): ImportResult
    {
        $fileImport = new FileImport($configuration->getAllowedFileExtensions());

        $form = $this->formFactory->create(FileImportType::class, $fileImport);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return ImportResult::selectFile($form, $this->getErrorMessages($form->getErrors()));
        }

        $matrix = null;
        $fileError = 'validation.file.unreadable';
        $file = $fileImport->getFile();
        try {
            // file is always set here, because form is valid
            $matrix = $file instanceof UploadedFile ? $this->matrixFactory->createFromUploadedFile($file, $csvFallbackEncoding) : null;
        } catch (FileEncodingException) {
            $fileError = 'validation.file.encoding';
        } catch (FileReadException) {
        }

        // validator is used also for unreadable file, so its message is translated the same way as other errors
        $errors = $matrix instanceof Matrix
            ? $this->validator->validate($matrix)
            : $this->validator->validate(false, new IsTrue(message: $fileError));

        if (!$matrix instanceof Matrix || $errors->count() > 0) {
            return ImportResult::selectFile($form, $this->getErrorMessages($errors));
        }

        $configurationError = $this->getConfigurationError($matrix, $configuration);
        if (null !== $configurationError) {
            return ImportResult::selectFile($form, [$this->createErrorMessage($configurationError)]);
        }

        $matrixForm = $this->createMatrixForm($matrix, $configuration, $saveActionUrl);
        // form is submitted, so errors of data from file are shown already in preview
        $this->submitMatrixForm($matrixForm, $matrix);

        return ImportResult::editMatrix($matrixForm, $matrix);
    }

    /**
     * Handles data sent from preview and imports it, when it is valid.
     *
     * @param string $saveActionUrl URL to which data is sent again, when preview with errors is shown
     */
    public function handleSave(Request $request, ImportConfigurationInterface $configuration, string $saveActionUrl): ImportResult
    {
        if ($this->isRequestDataTruncated($request)) {
            return ImportResult::finished([$this->createErrorMessage('error.data.truncated', ['%limit%' => $this->getMaxInputVars()])]);
        }

        $matrixData = $request->request->all()['matrix'] ?? null;
        $records = \is_array($matrixData) ? ($matrixData['records'] ?? null) : null;
        $matrix = \is_array($records) ? $this->matrixFactory->createFromPostData($records) : new Matrix();

        // also when data sent in request has invalid structure
        if (!$matrix->getRecords()) {
            return ImportResult::finished([$this->createErrorMessage('error.data.not_found')]);
        }

        $form = $this->createMatrixForm($matrix, $configuration, $saveActionUrl);
        $form->handleRequest($request);

        $messages = [];
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityImporter->import($matrix, $configuration);

                return ImportResult::finished([ImportMessage::success($this->translator->trans('success.import', [], self::TRANSLATION_DOMAIN))]);
            } catch (BatchEntityImportExceptionInterface $e) {
                $parameters = $e instanceof MatrixException ? $e->getTranslationParameters() : [];
                $messages[] = $this->createErrorMessage($e->getMessage(), $parameters);
            }
        }

        return ImportResult::editMatrix($form, $matrix, [...$messages, ...$this->getErrorMessages($form->getErrors())]);
    }

    /**
     * File which can't be imported with given configuration is rejected, instead of being imported only partially.
     *
     * @return string|null translation key of error
     */
    private function getConfigurationError(Matrix $matrix, ImportConfigurationInterface $configuration): ?string
    {
        // otherwise records which should update entities would be silently imported as new entities
        if (!$configuration->allowOverrideEntity() && array_any($matrix->getRecords(), static fn (MatrixRecord $record): bool => null !== $record->entityId && '' !== trim((string) $record->entityId))) {
            return 'error.file.entity_id_not_allowed';
        }

        // otherwise new entities would be created without any value from file
        if (!\in_array(true, $matrix->getHeaderInfo($configuration->getEntityClassName(), $configuration->getAllowedColumns()), true)) {
            return 'error.file.no_importable_columns';
        }

        return null;
    }

    private function createMatrixForm(Matrix $matrix, ImportConfigurationInterface $configuration, string $saveActionUrl): FormInterface
    {
        return $this->formFactory->create(
            MatrixType::class,
            $matrix,
            [
                'configuration' => $configuration,
                'action' => $saveActionUrl,
                'constraints' => $configuration->getMatrixConstraints(),
            ]
        );
    }

    private function submitMatrixForm(FormInterface $form, Matrix $matrix): void
    {
        $data = ['records' => array_map(static fn (MatrixRecord $record): array => $record->getData(), $matrix->getRecords())];

        // field to override entity is not a column of file, its value is taken from "entity_id" column
        $records = $matrix->getRecords();
        $recordForms = $form->has('records') ? $form->get('records') : [];
        foreach ($recordForms as $index => $recordForm) {
            if ($recordForm->has('entity') && isset($data['records'][$index], $records[$index])) {
                $data['records'][$index]['entity'] = self::getEntityFieldValue($recordForm->get('entity'), $records[$index]);
            }
        }

        $csrfTokenManager = $form->getConfig()->getOption('csrf_token_manager');
        if ($csrfTokenManager instanceof CsrfTokenManagerInterface) {
            $tokenId = $form->getConfig()->getOption('csrf_token_id');
            $data['_token'] = $csrfTokenManager->getToken(\is_string($tokenId) ? $tokenId : $form->getName())->getValue();
        }

        $form->submit($data);
    }

    /**
     * Field with lookup value (EntityLookupType, EntityLookupTextType) is filled already by its form type.
     * Default select (EntityType) is mapped to entity of record, which is not set yet, so it is empty.
     * Identifier from "entity_id" column is submitted then, so entity is set to record already in preview,
     * and constraints (e.g. DatabaseEntityUnique) know which entity is updated by record.
     */
    private static function getEntityFieldValue(FormInterface $field, MatrixRecord $record): mixed
    {
        $value = $field->getViewData();
        if ((null !== $value && '' !== $value) || null === $record->entityId) {
            return $value;
        }

        // values of choices are always strings, identifier of entity can be also an integer
        return trim((string) $record->entityId);
    }

    /**
     * PHP silently drops all fields above "max_input_vars" limit, so part of records would be imported
     * and the rest would be lost without any error. When number of received fields reaches the limit,
     * it is not possible to tell if something was dropped, so such request is rejected.
     */
    private function isRequestDataTruncated(Request $request): bool
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

    private function getMaxInputVars(): int
    {
        return (int) \ini_get('max_input_vars');
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createErrorMessage(string $key, array $parameters = []): ImportMessage
    {
        return ImportMessage::error($this->translator->trans($key, $parameters, self::TRANSLATION_DOMAIN));
    }

    /**
     * Only first error is returned.
     *
     * @param Traversable<mixed> $violations errors of form or violations returned by validator, both are translated already
     *
     * @return list<ImportMessage>
     */
    private function getErrorMessages(Traversable $violations): array
    {
        foreach ($violations as $error) {
            return $error instanceof FormError || $error instanceof ConstraintViolationInterface
                ? [ImportMessage::error((string) $error->getMessage())]
                : [];
        }

        return [];
    }
}

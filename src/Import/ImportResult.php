<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Import;

use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use LogicException;
use Symfony\Component\Form\FormInterface;

/**
 * Result of single step of import, it does not depend on HTTP response,
 * so it can be presented in any way (rendered view, redirect, JSON, etc.).
 *
 * @internal used by ImportControllerTrait, it is not covered by backward compatibility promise yet
 */
final readonly class ImportResult
{
    /**
     * @param list<ImportMessage> $messages
     */
    private function __construct(
        private ImportStep $step,
        private ?FormInterface $form,
        private ?Matrix $matrix,
        private array $messages,
    ) {
    }

    /**
     * @param list<ImportMessage> $messages
     */
    public static function selectFile(FormInterface $form, array $messages = []): self
    {
        return new self(ImportStep::SELECT_FILE, $form, null, $messages);
    }

    /**
     * @param list<ImportMessage> $messages
     */
    public static function editMatrix(FormInterface $form, Matrix $matrix, array $messages = []): self
    {
        return new self(ImportStep::EDIT_MATRIX, $form, $matrix, $messages);
    }

    /**
     * @param list<ImportMessage> $messages
     */
    public static function finished(array $messages): self
    {
        return new self(ImportStep::FINISHED, null, null, $messages);
    }

    public function getStep(): ImportStep
    {
        return $this->step;
    }

    /**
     * Form with file for step "SELECT_FILE", form with matrix for step "EDIT_MATRIX".
     *
     * @throws LogicException for step "FINISHED", which has no form
     */
    public function getForm(): FormInterface
    {
        return $this->form ?? throw new LogicException(\sprintf('Step "%s" has no form.', $this->step->name));
    }

    /**
     * @throws LogicException for step other than "EDIT_MATRIX"
     */
    public function getMatrix(): Matrix
    {
        return $this->matrix ?? throw new LogicException(\sprintf('Step "%s" has no matrix.', $this->step->name));
    }

    /**
     * @return list<ImportMessage>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Data was saved in database.
     */
    public function isImported(): bool
    {
        return ImportStep::FINISHED === $this->step
            && [] === array_filter($this->messages, static fn (ImportMessage $message): bool => ImportMessage::TYPE_ERROR === $message->type);
    }
}

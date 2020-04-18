<?php

namespace JG\BatchEntityImportBundle\Model;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class FileImport
{
    private const AVAILABLE_FILE_EXTENSIONS = ['csv', 'ods', 'xls', 'xlsx'];
    /**
     * @Assert\File()
     * @Assert\NotNull()
     */
    private ?UploadedFile $file = null;

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(UploadedFile $file): void
    {
        $this->file = $file;
    }

    /**
     * @Assert\Callback
     *
     * @param ExecutionContextInterface $context
     */
    public function validateExtension(ExecutionContextInterface $context): void
    {
        if (!$this->file || in_array($this->file->getClientOriginalExtension(), self::AVAILABLE_FILE_EXTENSIONS, true)) {
            return;
        }

        $context
            ->buildViolation('validation.file.extension', ['%extensions' => implode(', ', self::AVAILABLE_FILE_EXTENSIONS)])
            ->atPath('file')
            ->addViolation();
    }
}

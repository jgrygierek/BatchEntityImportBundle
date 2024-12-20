<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class FileImport
{
    #[Assert\File]
    #[Assert\NotNull]
    private ?UploadedFile $file = null;

    public function __construct(private readonly array $allowedExtensions = [])
    {
    }

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(UploadedFile $file): void
    {
        $this->file = $file;
    }

    #[Assert\Callback]
    public function validateExtensions(ExecutionContextInterface $context): void
    {
        $extensions = array_map('strtolower', $this->allowedExtensions);
        if (!in_array(strtolower($this->file->getClientOriginalExtension()), $extensions, true)) {
            $context->buildViolation('validation.file.extension', ['%extensions' => implode(', ', $extensions)])->addViolation();
        }
    }
}

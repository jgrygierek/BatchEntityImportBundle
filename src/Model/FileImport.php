<?php

namespace JG\BatchEntityImportBundle\Model;

use JG\BatchEntityImportBundle\Validator\Constraints as CustomAssert;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class FileImport
{
    /**
     * @Assert\File()
     * @Assert\NotNull()
     * @CustomAssert\FileExtension({"csv", "xls", "xlsx", "ods"})
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
}

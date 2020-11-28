<?php

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class FileExtension extends Constraint
{
    public string  $message = 'validation.file.extension';
    public array   $extensions = [];

    public function getDefaultOption(): string
    {
        return 'extensions';
    }

    public function getRequiredOptions(): array
    {
        return ['extensions'];
    }
}

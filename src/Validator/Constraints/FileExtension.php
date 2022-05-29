<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute]
class FileExtension extends Constraint
{
    public string $message = 'validation.file.extension';
    public array $extensions = [];

    public function getDefaultOption(): string
    {
        return 'extensions';
    }

    public function getRequiredOptions(): array
    {
        return ['extensions'];
    }
}

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class DatabaseEntityUniqueValue extends Constraint
{
    public string $message = 'validation.matrix.record.value.unique';
    public string $entityClassName;
    public array $fields;

    public function getDefaultOption(): string
    {
        return 'entityClassName';
    }

    public function getRequiredOptions(): array
    {
        return ['entityClassName', 'fields'];
    }
}

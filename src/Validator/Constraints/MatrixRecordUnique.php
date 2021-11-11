<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class MatrixRecordUnique extends Constraint
{
    public string $message = 'validation.matrix.record.unique';
    public array $fields;

    public function getDefaultOption(): string
    {
        return 'fields';
    }

    public function getRequiredOptions(): array
    {
        return ['fields'];
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}

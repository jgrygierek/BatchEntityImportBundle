<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use InvalidArgumentException;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class MatrixRecordUnique extends Constraint
{
    public string $message = 'validation.matrix.record.unique';
    public array $fields = [];

    public function __construct(mixed $options = null, array $groups = null, mixed $payload = null)
    {
        parent::__construct($options, $groups, $payload);

        if (empty($options['fields'])) {
            throw new InvalidArgumentException('Option "fields" should not be empty.');
        }
    }

    public function getDefaultOption(): string
    {
        return 'fields';
    }

    public function getRequiredOptions(): array
    {
        return ['fields'];
    }
}

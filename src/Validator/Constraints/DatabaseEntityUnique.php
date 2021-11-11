<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use InvalidArgumentException;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class DatabaseEntityUnique extends Constraint
{
    public string $message = 'validation.entity.unique';
    public string $entityClassName;
    public array $fields;

    public function __construct($options = null, array $groups = null, $payload = null)
    {
        parent::__construct($options, $groups, $payload);

        if (empty($options['fields'])) {
            throw new InvalidArgumentException('Option "fields" should not be empty.');
        }
    }

    public function getDefaultOption(): string
    {
        return 'entityClassName';
    }

    public function getRequiredOptions(): array
    {
        return ['entityClassName', 'fields'];
    }
}

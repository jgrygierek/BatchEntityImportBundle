<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use InvalidArgumentException;
use Symfony\Component\Validator\Constraint;

class MatrixRecordUnique extends Constraint
{
    public string $message = 'validation.matrix.record.unique';

    /**
     * @param string[] $fields
     * @param string[]|null $groups
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public array $fields,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if ([] === $fields) {
            throw new InvalidArgumentException('Option "fields" should not be empty.');
        }

        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}

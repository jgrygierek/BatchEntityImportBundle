<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use InvalidArgumentException;
use Symfony\Component\Validator\Constraint;

class DatabaseEntityUnique extends Constraint
{
    public string $message = 'validation.entity.unique';

    /**
     * @param string[] $fields
     * @param string[]|null $groups
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $entityClassName,
        public array $fields,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if ([] === $fields) {
            throw new InvalidArgumentException('Option "fields" should not be empty.');
        }

        if ('' === $entityClassName) {
            throw new InvalidArgumentException('Option "entityClassName" should not be empty.');
        }

        parent::__construct(groups: $groups, payload: $payload);

        $this->message = $message ?? $this->message;
    }
}

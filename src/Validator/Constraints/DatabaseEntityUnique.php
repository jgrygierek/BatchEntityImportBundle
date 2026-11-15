<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use InvalidArgumentException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidOptionsException;

class DatabaseEntityUnique extends Constraint
{
    use LegacyOptionsTrait;

    public string $message = 'validation.entity.unique';
    public string $entityClassName = '';
    /**
     * @var string[]
     */
    public array $fields = [];

    /**
     * Recommended usage: new DatabaseEntityUnique(entityClassName: Entity::class, fields: ['field_name']).
     * Array of options passed as first argument is still supported.
     *
     * @param array<string, mixed>|null $options
     * @param string[]|null $groups
     * @param string[]|null $fields
     *
     * @throws InvalidArgumentException
     * @throws InvalidOptionsException
     */
    public function __construct(
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
        ?string $entityClassName = null,
        ?array $fields = null,
        ?string $message = null,
    ) {
        $options = $this->normalizeLegacyOptions($options, ['entityClassName', 'fields', 'message', 'groups', 'payload']);

        parent::__construct(null, $groups ?? $this->getStringListOption($options, 'groups'), $payload ?? $options['payload'] ?? null);

        $fields ??= $this->getStringListOption($options, 'fields') ?? [];
        if (empty($fields)) {
            throw new InvalidArgumentException('Option "fields" should not be empty.');
        }

        $entityClassName ??= $this->getStringOption($options, 'entityClassName') ?? '';
        if ('' === $entityClassName) {
            throw new InvalidArgumentException('Option "entityClassName" should not be empty.');
        }

        $this->fields = $fields;
        $this->entityClassName = $entityClassName;
        $this->message = $message ?? $this->getStringOption($options, 'message') ?? $this->message;
    }
}

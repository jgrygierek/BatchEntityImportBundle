<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Event;

use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;

/**
 * Dispatched for each imported value, before it is set to entity (or to its translation).
 * Listener can change the value, e.g. convert string to date or enum.
 *
 * Exception thrown by listener stops import and changes made so far are reverted.
 * To show message about invalid value to user, throw MatrixRecordInvalidDataTypeException.
 */
final class RecordValueEvent
{
    /**
     * @param object $entity entity to which value is set
     * @param int $rowNumber number of record, starting from 1, as in the preview
     */
    public function __construct(
        public readonly ImportConfigurationInterface $configuration,
        public readonly MatrixRecord $record,
        public readonly object $entity,
        public readonly string $columnName,
        public readonly int $rowNumber,
        private mixed $value,
    ) {
    }

    /**
     * Value from file, already converted for array fields and related entities.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}

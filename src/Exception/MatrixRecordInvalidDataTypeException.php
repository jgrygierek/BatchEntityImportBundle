<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Exception;

use Throwable;

/**
 * Exception thrown when data of record have invalid type.
 * It should be thrown in case of insufficient data validation.
 */
class MatrixRecordInvalidDataTypeException extends MatrixException
{
    /**
     * @param array<string, string|int> $translationParameters
     */
    public function __construct(
        string $message = 'exception.matrix.record.data.invalid_type',
        array $translationParameters = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->translationParameters = $translationParameters;
    }

    /**
     * @param int|null $rowNumber number of record, starting from 1, as in the preview; without it message has no position
     */
    public static function forField(?int $rowNumber, string $columnName, ?Throwable $previous = null): self
    {
        if (null === $rowNumber) {
            return new self(previous: $previous);
        }

        return new self(
            'exception.matrix.record.data.invalid_type_in_field',
            ['%row%' => $rowNumber, '%column%' => $columnName],
            $previous,
        );
    }
}

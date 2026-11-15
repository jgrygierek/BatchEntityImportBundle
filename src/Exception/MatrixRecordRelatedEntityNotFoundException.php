<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Exception;

use Throwable;

/**
 * Exception thrown when related entity can't be found by value from record.
 * It should be thrown in case of insufficient data validation.
 */
class MatrixRecordRelatedEntityNotFoundException extends MatrixException
{
    /**
     * @param array<string, string|int> $translationParameters
     */
    public function __construct(
        string $message = 'exception.matrix.record.related_entity.not_found',
        array $translationParameters = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->translationParameters = $translationParameters;
    }

    /**
     * @param int|null $rowNumber number of record, starting from 1, as in the preview; without it message has no position
     */
    public static function forField(?int $rowNumber, string $columnName): self
    {
        if (null === $rowNumber) {
            return new self();
        }

        return new self(
            'exception.matrix.record.related_entity.not_found_in_field',
            ['%row%' => $rowNumber, '%column%' => $columnName],
        );
    }
}

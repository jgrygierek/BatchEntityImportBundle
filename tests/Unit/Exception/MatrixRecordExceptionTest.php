<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Exception;

use JG\BatchEntityImportBundle\Exception\MatrixRecordInvalidDataTypeException;
use JG\BatchEntityImportBundle\Exception\MatrixRecordRelatedEntityNotFoundException;
use PHPUnit\Framework\TestCase;
use TypeError;

class MatrixRecordExceptionTest extends TestCase
{
    public function testInvalidDataTypeWithPosition(): void
    {
        $previous = new TypeError();
        $exception = new MatrixRecordInvalidDataTypeException(5, 'name:en', $previous);

        self::assertSame('exception.matrix.record.data.invalid_type_in_field', $exception->getMessage());
        self::assertSame(['%row%' => 5, '%column%' => 'name:en'], $exception->getTranslationParameters());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testRelatedEntityNotFoundWithPosition(): void
    {
        $exception = new MatrixRecordRelatedEntityNotFoundException(2, 'category');

        self::assertSame('exception.matrix.record.related_entity.not_found_in_field', $exception->getMessage());
        self::assertSame(['%row%' => 2, '%column%' => 'category'], $exception->getTranslationParameters());
    }
}

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
        $exception = MatrixRecordInvalidDataTypeException::forField(5, 'name:en', $previous);

        self::assertSame('exception.matrix.record.data.invalid_type_in_field', $exception->getMessage());
        self::assertSame(['%row%' => 5, '%column%' => 'name:en'], $exception->getTranslationParameters());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testInvalidDataTypeWithoutRowNumber(): void
    {
        $previous = new TypeError();
        $exception = MatrixRecordInvalidDataTypeException::forField(null, 'name', $previous);

        self::assertSame('exception.matrix.record.data.invalid_type', $exception->getMessage());
        self::assertSame([], $exception->getTranslationParameters());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testDefaultMessagesAreNotChanged(): void
    {
        self::assertSame('exception.matrix.record.data.invalid_type', new MatrixRecordInvalidDataTypeException()->getMessage());
        self::assertSame([], new MatrixRecordInvalidDataTypeException()->getTranslationParameters());
        self::assertSame('exception.matrix.record.related_entity.not_found', new MatrixRecordRelatedEntityNotFoundException()->getMessage());
        self::assertSame([], new MatrixRecordRelatedEntityNotFoundException()->getTranslationParameters());
    }

    public function testRelatedEntityNotFoundWithPosition(): void
    {
        $exception = MatrixRecordRelatedEntityNotFoundException::forField(2, 'category');

        self::assertSame('exception.matrix.record.related_entity.not_found_in_field', $exception->getMessage());
        self::assertSame(['%row%' => 2, '%column%' => 'category'], $exception->getTranslationParameters());
    }

    public function testRelatedEntityNotFoundWithoutRowNumber(): void
    {
        $exception = MatrixRecordRelatedEntityNotFoundException::forField(null, 'category');

        self::assertSame('exception.matrix.record.related_entity.not_found', $exception->getMessage());
        self::assertSame([], $exception->getTranslationParameters());
    }
}

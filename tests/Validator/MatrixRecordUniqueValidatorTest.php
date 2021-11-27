<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Validator;

use Generator;
use InvalidArgumentException;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixFactory;
use JG\BatchEntityImportBundle\Validator\Constraints\MatrixRecordUnique;
use JG\BatchEntityImportBundle\Validator\Constraints\MatrixRecordUniqueValidator;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class MatrixRecordUniqueValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): MatrixRecordUniqueValidator
    {
        return new MatrixRecordUniqueValidator();
    }

    /**
     * @dataProvider dataProvider
     */
    public function testNoDuplication(array $fields, array $data): void
    {
        $matrix = MatrixFactory::createFromPostData($data);
        $constraint = new MatrixRecordUnique(['fields' => $fields]);
        $this->validator->validate($matrix, $constraint);
        $this->assertNoViolation();
    }

    public function dataProvider(): Generator
    {
        yield [
            'fields' => ['field_1'],
            'data' => [
                [
                    'field_1' => 0,
                    'field_2' => 100,
                ],
                [
                    'field_1' => 1,
                    'field_2' => 101,
                ],
                [
                    'field_1' => 2,
                    'field_2' => 102,
                ],
                [
                    'field_1' => 3,
                    'field_2' => 201,
                ],
                [
                    'field_1' => 4,
                    'field_2' => 102,
                ],
            ],
        ];

        yield [
            'fields' => ['field_1', 'field_2'],
            'data' => [
                [
                    'field_1' => 0,
                    'field_2' => 100,
                ],
                [
                    'field_1' => 1,
                    'field_2' => 101,
                ],
                [
                    'field_1' => 1,
                    'field_2' => 102,
                ],
                [
                    'field_1' => 2,
                    'field_2' => 102,
                ],
                [
                    'field_1' => 3,
                    'field_2' => 103,
                ],
            ],
        ];
    }

    /**
     * @dataProvider duplicatedDataProvider
     */
    public function testDuplications(array $fields, array $expectedDuplicatedRecords, array $data): void
    {
        $matrix = MatrixFactory::createFromPostData($data);
        $constraint = new MatrixRecordUnique(['fields' => $fields]);
        $this->validator->validate($matrix, $constraint);
        $violations = $this->context->getViolations();
        $this->assertCount(count($expectedDuplicatedRecords), $violations);

        $matrixRecords = $matrix->getRecords();
        foreach ($expectedDuplicatedRecords as $i => $expectedDuplicatedRecordIndex) {
            $this->assertSame(
                $matrixRecords[$expectedDuplicatedRecordIndex],
                $violations->get($i)->getInvalidValue()
            );
        }
    }

    public function duplicatedDataProvider(): Generator
    {
        yield [
            'fields' => ['field_1'],
            'duplicated_records' => [1, 3],
            'data' => [
                [
                    'field_1' => 0,
                    'field_2' => 100,
                ],
                [
                    'field_1' => 0,
                    'field_2' => 101,
                ],
                [
                    'field_1' => 1,
                    'field_2' => 101,
                ],
                [
                    'field_1' => 1,
                    'field_2' => 101,
                ],
                [
                    'field_1' => 2,
                    'field_2' => 102,
                ],
            ],
        ];

        yield [
            'fields' => ['field_1', 'field_2'],
            'duplicated_records' => [3],
            'data' => [
                [
                    'field_1' => 0,
                    'field_2' => 100,
                ],
                [
                    'field_1' => 0,
                    'field_2' => 101,
                ],
                [
                    'field_1' => 1,
                    'field_2' => 101,
                ],
                [
                    'field_1' => 1,
                    'field_2' => 101,
                ],
                [
                    'field_1' => 2,
                    'field_2' => 102,
                ],
            ],
        ];
    }

    public function testInvalidFieldNameException(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Option "fields" contains invalid data.'));
        $data = [
            [
                'field_1' => 0,
                'field_2' => 100,
            ],
            [
                'field_1' => 1,
                'field_2' => 101,
            ],
        ];
        $matrix = MatrixFactory::createFromPostData($data);
        $constraint = new MatrixRecordUnique(['fields' => ['field_3']]);
        $this->validator->validate($matrix, $constraint);
    }

    public function testConstraintException(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Option "fields" should not be empty.'));
        new MatrixRecordUnique(['fields' => []]);
    }

    public function testValidatedValueException(): void
    {
        $value = 'qwerty';
        $this->expectExceptionObject(new UnexpectedTypeException($value, Matrix::class));
        $this->validator->validate($value, new MatrixRecordUnique(['fields' => ['abcd']]));
    }

    public function testValidatorConstraintException(): void
    {
        $constraint = new Blank();
        $this->expectExceptionObject(new UnexpectedTypeException($constraint, MatrixRecordUnique::class));
        $this->validator->validate(MatrixFactory::createFromPostData([]), new Blank());
    }
}

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Model\Matrix;

use Generator;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Tests\AbstractValidationTestCase;

class MatrixRecordTest extends AbstractValidationTestCase
{
    /**
     * @dataProvider getValidDataProvider
     */
    public function testData(array $recordData, array $expectedRecordData): void
    {
        $matrixRecord = new MatrixRecord($recordData);
        self::assertSame($expectedRecordData, $matrixRecord->getData());
    }

    public function getValidDataProvider(): Generator
    {
        yield [
            [
                'column_name' => '',
                '' => ' ',
                'column name2' => '',
                'column-name3' => '',
                '1' => '',
            ],
            [
                'column_name' => '',
                'column_name2' => '',
                'column-name3' => '',
                '1' => '',
            ],
        ];
        yield [['' => '', ' ' => ''], []];
    }
}

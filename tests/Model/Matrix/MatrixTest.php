<?php

namespace JG\BatchEntityImportBundle\Tests\Model\Matrix;

use Generator;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Tests\AbstractValidationTestCase;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;

class MatrixTest extends AbstractValidationTestCase
{
    /**
     * @dataProvider getValidDataProvider
     *
     * @param array $header
     * @param array $recordsData
     */
    public function testValidMatrix(array $header, array $recordsData): void
    {
        $matrix = new Matrix($header, $recordsData);
        $this->assertEmpty($this->getErrors($matrix));
    }

    public function getValidDataProvider(): Generator
    {
        yield [['column_name'], [['column_name' => '']]];
        yield [['column_name'], [['column_name' => '', ' ' => '']]];
        yield [['', 'column_name'], [['column_name' => '']]];
    }

    /**
     * @dataProvider getInvalidDataProvider
     *
     * @param array $header
     * @param array $recordsData
     */
    public function testInvalidMatrix(array $header, array $recordsData): void
    {
        $matrix = new Matrix($header, $recordsData);
        $this->assertNotEmpty($this->getErrors($matrix));
    }

    public function getInvalidDataProvider(): Generator
    {
        yield [[], [['column_name' => '']]];
        yield [[' '], [['column_name' => '']]];
        yield [['wrong-column-name'], [['column_name' => '']]];
        yield [['column_name'], []];
        yield [['column_name'], [[' ' => '']]];
    }

    public function testRemoveColumnsWithEmptyHeader(): void
    {
        $header      = ['column_name', '', 'column_name2', null, ' '];
        $recordsData = [
            ['column_name' => '', '' => '', 'column_name2' => '', ' ' => ''],
        ];

        $matrix = new Matrix($header, $recordsData);

        $expectedHeader = ['column_name', 'column_name2'];
        $this->assertSame($expectedHeader, $matrix->getHeader());

        $expectedRecords = [new MatrixRecord(['column_name' => '', 'column_name2' => ''])];
        $this->assertEquals($expectedRecords, $matrix->getRecords());
    }

    public function testHeaderInfoForNonTranslatableEntity(): void
    {
        $expected = [
            'unknown_column_name' => false,
            'test_property'       => true,
            'test_property:en'    => false,
        ];

        $matrix = new Matrix(array_keys($expected));

        $this->assertSame($expected, $matrix->getHeaderInfo(TestEntity::class));
    }

    public function testHeaderInfoForTranslatableEntity(): void
    {
        $expected = [
            'unknown_column_name'          => false,
            'test_property'                => true,
            'test_property:en'             => false,
            'test_translation_property'    => false,
            'test_translation_property:en' => true,
        ];

        $matrix = new Matrix(array_keys($expected));

        $this->assertSame($expected, $matrix->getHeaderInfo(TranslatableEntity::class));
    }
}

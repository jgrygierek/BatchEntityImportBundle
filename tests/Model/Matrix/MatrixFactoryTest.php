<?php

namespace JG\BatchEntityImportBundle\Tests\Model\Matrix;

use Generator;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Yectep\PhpSpreadsheetBundle\Factory;

class MatrixFactoryTest extends TestCase
{
    /**
     * @dataProvider dataProvider
     *
     * @param string $fileExtension
     *
     * @throws SpreadsheetException
     * @throws Exception
     */
    public function testCreateFromUploadedFileSuccess(string $fileExtension): void
    {
        foreach ($this->contentProvider() as $data) {
            $file   = $this->createFile($fileExtension, $data[0]);
            $matrix = MatrixFactory::createFromUploadedFile($file);

            $this->assertEquals($data[1], !empty($matrix->getHeader()));
            $this->assertCount($data[2], $matrix->getRecords());
        }
    }

    public function dataProvider(): Generator
    {
        yield ['csv'];
        yield ['xls'];
        yield ['xlsx'];
        yield ['ods'];
        yield ['CSV'];
        yield ['XLS'];
        yield ['XLSX'];
        yield ['ODS'];
    }

    public function contentProvider(): Generator
    {
        yield[[['header1', 'header2', 'header3'], ['aaaa', 'bbbb', '123'], ['xxxx', 'yyyy', '456']], true, 2];
        yield[[['header1', 'header2', 'header3']], true, 0];
        yield[[[null]], true, 0];
        yield[[], true, 0];
    }

    /**
     * @dataProvider postContentProvider
     *
     * @param array $data
     * @param bool  $isHeader
     * @param int   $recordsNumber
     */
    public function testCreateFromPostDataSuccess(array $data, bool $isHeader, int $recordsNumber): void
    {
        $matrix = MatrixFactory::createFromPostData($data);
        $this->assertEquals($isHeader, !empty($matrix->getHeader()));
        $this->assertCount($recordsNumber, $matrix->getRecords());
    }

    public function postContentProvider(): Generator
    {
        yield[[['aaaa', 'bbbb', '123'], ['xxxx', 'yyyy', '456']], true, 2];
        yield[[['aaaa', 'bbbb', '123']], true, 1];
        yield[[], false, 0];
    }

    /**
     * @param string $fileExtension
     * @param array  $data
     *
     * @return UploadedFile
     * @throws Exception
     * @throws SpreadsheetException
     * @throws WriterException
     */
    private function createFile(string $fileExtension, array $data = []): UploadedFile
    {
        $fileExtension = strtolower($fileExtension);
        $filename      = 'file.' . $fileExtension;
        $factory       = new Factory();

        $spreadsheet = $factory->createSpreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($data);

        $writer = $factory->createWriter($spreadsheet, ucfirst($fileExtension));
        $writer->save($filename);

        return new UploadedFile($filename, $filename);
    }
}

<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Model\Matrix;

use Generator;
use InvalidArgumentException;
use JG\BatchEntityImportBundle\Enums\CsvDelimiterEnum;
use JG\BatchEntityImportBundle\Exception\FileEncodingException;
use JG\BatchEntityImportBundle\Exception\FileReadException;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixFactory;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MatrixFactoryTest extends TestCase
{
    /** @var string[] */
    private array $temporaryFiles = [];

    #[DataProvider('importFilesDataProvider')]
    public function testCreateFromRealUploadedFileSuccess(string $file, array $expectedHeader, int $expectedRecordNumber): void
    {
        $uploadedFile = new UploadedFile($file, $file);
        $matrix = MatrixFactory::createFromUploadedFile($uploadedFile);

        $this->assertSame($expectedHeader, $matrix->getHeader());
        $this->assertCount($expectedRecordNumber, $matrix->getRecords());
    }

    public static function importFilesDataProvider(): Generator
    {
        yield [
            __DIR__ . '/../../../Fixtures/Resources/test.csv',
            ['test_private_property', 'test-private-property2', 'test_public_property', 'test_array_field'],
            30,
        ];
        yield [
            __DIR__ . '/../../../Fixtures/Resources/test.xls',
            ['test_private_property', 'test-private-property2', 'test_public_property', 'test_array_field'],
            30,
        ];
        yield [
            __DIR__ . '/../../../Fixtures/Resources/test.xlsx',
            ['test_private_property', 'test-private-property2', 'test_public_property', 'test_array_field'],
            30,
        ];
        yield [__DIR__ . '/../../../Fixtures/Resources/test_import_with_rows_without_cells.xlsx', ['Domain', 'Another', 'Comment'], 24];
    }

    /**
     * @throws SpreadsheetException
     * @throws Exception
     */
    #[DataProvider('dataProvider')]
    public function testCreateFromGeneratedUploadFileSuccess(string $fileExtension, CsvDelimiterEnum $delimiter = CsvDelimiterEnum::COMMA): void
    {
        foreach ($this->contentProvider() as $data) {
            $file = $this->createFile($fileExtension, $delimiter, \array_merge([$data['header']], $data['records']));
            $matrix = MatrixFactory::createFromUploadedFile($file);

            self::assertCount(count($data['expectedHeader']), $matrix->getHeader());
            self::assertCount(count($data['expectedRecords']), $matrix->getRecords());

            foreach ($data['expectedRecords'] as $index => $record) {
                self::assertSame($record, \array_values($matrix->getRecords()[$index]->getData()));
            }

            unlink($file->getPathname());
        }
    }

    public static function dataProvider(): Generator
    {
        yield ['csv', CsvDelimiterEnum::COMMA];
        yield ['csv', CsvDelimiterEnum::SEMICOLON];
        yield ['xls'];
        yield ['xlsx'];
        yield ['ods'];
        yield ['CSV', CsvDelimiterEnum::COMMA];
        yield ['CSV', CsvDelimiterEnum::SEMICOLON];
        yield ['XLS'];
        yield ['XLSX'];
        yield ['ODS'];
    }

    public function contentProvider(): Generator
    {
        yield [
            'header' => ['header1', 'header2', 'header3'],
            'expectedHeader' => ['header1', 'header2', 'header3'],
            'records' => [['aaaa', 'bbbb', 'cccc'], ['xxxx', 'yyyy', 'zzzz']],
            'expectedRecords' => [['aaaa', 'bbbb', 'cccc'], ['xxxx', 'yyyy', 'zzzz']],
        ];
        yield [
            'header' => ['header1', 'header2', 'header3'],
            'expectedHeader' => ['header1', 'header2', 'header3'],
            'records' => [],
            'expectedRecords' => [],
        ];
        yield [
            'header' => ['header1:en'],
            'expectedHeader' => ['header1:en'],
            'records' => [['aaaa']],
            'expectedRecords' => [['aaaa']],
        ];
        yield [
            'header' => [null],
            'expectedHeader' => [],
            'records' => [['abcd']],
            'expectedRecords' => [],
        ];
        yield [
            'header' => [],
            'expectedHeader' => [],
            'records' => [],
            'expectedRecords' => [],
        ];
    }

    /**
     * @throws Exception
     * @throws SpreadsheetException
     */
    public function testCreateFromUploadedFileWrongExtension(): void
    {
        $this->expectExceptionMessage('Reader for extension Txt is not supported by PhpOffice.');

        $file = new UploadedFile(__DIR__ . '/../../../Fixtures/Resources/test_wrong_extension.txt', 'test_wrong_extension.txt');
        MatrixFactory::createFromUploadedFile($file);
    }

    #[DataProvider('postContentProvider')]
    public function testCreateFromPostDataSuccess(array $data, bool $isHeader, int $recordsNumber): void
    {
        $matrix = MatrixFactory::createFromPostData($data);
        self::assertEquals($isHeader, !empty($matrix->getHeader()));
        self::assertCount($recordsNumber, $matrix->getRecords());
    }

    public static function postContentProvider(): Generator
    {
        yield [[['aaaa', 'bbbb', '123'], ['xxxx', 'yyyy', '456']], true, 2];
        yield [[['aaaa', 'bbbb', '123']], true, 1];
        yield [[['' => null], ['' => 'abcd']], false, 0];
        yield [[], false, 0];
        yield 'numeric names of columns' => [[['0' => 'a', '2024' => 'b']], true, 1];
        yield 'records without first index' => [[1 => ['name' => 'a'], 5 => ['name' => 'b']], true, 2];
        yield 'row is not an array' => [[['name' => 'a'], 'abcd'], false, 0];
        yield 'value is not scalar' => [[['name' => ['a']]], false, 0];
        yield 'invalid name of column' => [[['name' => 'a'], ['na.me' => 'b']], false, 0];
        yield 'name of column with new line' => [[["name\n" => 'a']], false, 0];
    }

    public function testEmptyRowsAreSkipped(): void
    {
        $matrix = MatrixFactory::createFromUploadedFile($this->createCsvFile("name,age\nJan,5\n\n,\n  ,\nEwa,0\n"));

        self::assertSame(
            [['name' => 'Jan', 'age' => '5'], ['name' => 'Ewa', 'age' => '0']],
            array_map(static fn (MatrixRecord $record): array => $record->getData(), $matrix->getRecords()),
        );
    }

    public function testDelimiterUsedInRecordsDoesNotChangeDetectedDelimiter(): void
    {
        $matrix = MatrixFactory::createFromUploadedFile($this->createCsvFile("name;description\nJan;\"a, b, c, d\"\nEwa;\"e, f, g\"\n"));

        self::assertSame(['name', 'description'], $matrix->getHeader());
        self::assertSame(['name' => 'Jan', 'description' => 'a, b, c, d'], $matrix->getRecords()[0]->getData());
    }

    #[DataProvider('encodingProvider')]
    public function testCsvEncoding(string $content, ?string $fallbackEncoding, string $expectedName): void
    {
        $file = $this->createCsvFile($content);
        $matrix = null === $fallbackEncoding
            ? MatrixFactory::createFromUploadedFile($file)
            : MatrixFactory::createFromUploadedFile($file, $fallbackEncoding);

        self::assertSame($expectedName, $matrix->getRecords()[0]->getData()['name']);
    }

    public static function encodingProvider(): Generator
    {
        yield 'UTF-8' => ["name;city\nŻaneta;Łódź\n", null, 'Żaneta'];
        yield 'UTF-8 with BOM' => ["\xEF\xBB\xBFname;city\nŻaneta;Łódź\n", null, 'Żaneta'];
        yield 'UTF-8, fallback is not used' => ["name;city\nŻaneta;Łódź\n", 'CP1250', 'Żaneta'];
        yield 'UTF-16 with BOM' => ["\xFF\xFE" . iconv('UTF-8', 'UTF-16LE', "name;city\nŻaneta;Łódź\n"), null, 'Żaneta'];
        yield 'fallback CP1250' => [(string) iconv('UTF-8', 'CP1250', "name;city\nŻaneta;Łódź\n"), 'CP1250', 'Żaneta'];
        yield 'fallback CP1252' => [(string) iconv('UTF-8', 'CP1252', "name;city\nRené;Orléans\n"), 'CP1252', 'René'];
    }

    /**
     * Whole text has to be the same after import, not only single characters.
     */
    #[DataProvider('fallbackEncodingProvider')]
    public function testCsvFallbackEncodingKeepsAllCharacters(string $encoding, string $text): void
    {
        $content = iconv('UTF-8', $encoding, "name;text\nabc;" . $text . "\n");
        self::assertIsString($content);

        $matrix = MatrixFactory::createFromUploadedFile($this->createCsvFile($content), $encoding);

        self::assertSame(['name' => 'abc', 'text' => $text], $matrix->getRecords()[0]->getData());
    }

    public static function fallbackEncodingProvider(): Generator
    {
        yield 'polish, CP1250' => ['CP1250', 'ąćęłńóśźż ĄĆĘŁŃÓŚŹŻ Zażółć gęślą jaźń'];
        yield 'polish, ISO-8859-2' => ['ISO-8859-2', 'ąćęłńóśźż ĄĆĘŁŃÓŚŹŻ Zażółć gęślą jaźń'];
        yield 'czech, CP1250' => ['CP1250', 'Příliš žluťoučký kůň úpěl ďábelské ódy'];
        yield 'german and french, CP1252' => ['CP1252', 'Größe Straße Œuvre garçon naïve €'];
        yield 'cyrillic, CP1251' => ['CP1251', 'Съешь же ещё этих мягких французских булок'];
        yield 'greek, CP1253' => ['CP1253', 'Ξεσκεπάζω την ψυχοφθόρα βδελυγμία'];
        yield 'turkish, CP1254' => ['CP1254', 'Pijamalı hasta yağız şoföre çabucak güvendi'];
        yield 'japanese, Shift_JIS' => ['Shift_JIS', 'いろはにほへと 日本語 カタカナ'];
        yield 'chinese, GBK' => ['GBK', '中文字符 测试'];
        yield 'korean, EUC-KR' => ['EUC-KR', '한국어 테스트'];
    }

    /**
     * File which is already in Unicode has to be read correctly, no matter which fallback encoding is configured.
     */
    #[DataProvider('unicodeTextProvider')]
    public function testUnicodeCsvIsNotAffectedByFallbackEncoding(string $text): void
    {
        $matrix = MatrixFactory::createFromUploadedFile($this->createCsvFile("name;text\nabc;" . $text . "\n"), 'CP1250');

        self::assertSame($text, $matrix->getRecords()[0]->getData()['text']);
    }

    public static function unicodeTextProvider(): Generator
    {
        yield 'polish' => ['Zażółć gęślą jaźń'];
        yield 'mixed alphabets' => ['Łódź Москва Αθήνα 東京 서울'];
        yield 'emoji' => ['😀 👍🏽 🇵🇱'];
        yield 'combining characters' => ["e\u{301} a\u{308}"];
    }

    public function testCsvWithUnknownEncodingIsRejected(): void
    {
        $file = $this->createCsvFile((string) iconv('UTF-8', 'CP1250', "name;city\nŻaneta;Łódź\n"));

        $this->expectException(FileEncodingException::class);
        MatrixFactory::createFromUploadedFile($file);
    }

    public function testUnsupportedFallbackEncoding(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('CSV fallback encoding "CP125O" is not supported.'));
        MatrixFactory::createFromUploadedFile($this->createCsvFile("name\nJan\n"), 'CP125O');
    }

    public function testEmptyFallbackEncodingIsTreatedAsNotPassed(): void
    {
        $file = $this->createCsvFile((string) iconv('UTF-8', 'CP1250', "name;city\nŻaneta;Łódź\n"));

        $this->expectException(FileEncodingException::class);
        MatrixFactory::createFromUploadedFile($file, '');
    }

    #[DataProvider('entityIdHeaderProvider')]
    public function testEntityIdColumn(string $header, array $expectedIds): void
    {
        $matrix = MatrixFactory::createFromUploadedFile($this->createCsvFile($header . ",name\n2,a\n,b\n10,c\n"));

        self::assertSame(['name'], $matrix->getHeader());
        self::assertSame($expectedIds, array_map(static fn (MatrixRecord $record): int|string|null => $record->entityId, $matrix->getRecords()));
    }

    public static function entityIdHeaderProvider(): Generator
    {
        yield ['entity_id', ['2', null, '10']];
        yield 'with space' => ['entity id', ['2', null, '10']];
        yield 'with surrounding spaces' => [' entity_id ', ['2', null, '10']];
    }

    public function testHeaderNamesAreNormalized(): void
    {
        $matrix = MatrixFactory::createFromUploadedFile($this->createCsvFile("0, first name ,entity id\na,b,5\n"));

        self::assertSame(['0', 'first_name'], $matrix->getHeader());
        self::assertSame(['0' => 'a', 'first_name' => 'b'], $matrix->getRecords()[0]->getData());
    }

    public function testNotExistingCsvFile(): void
    {
        $this->expectException(FileReadException::class);
        MatrixFactory::createFromUploadedFile(new UploadedFile(sys_get_temp_dir() . '/batch_entity_import_not_existing.csv', 'file.csv', null, \UPLOAD_ERR_NO_FILE, true));
    }

    #[DataProvider('unreadableFileProvider')]
    public function testUnreadableFile(string $extension): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'bei');
        file_put_contents($path, 'this is not a spreadsheet');
        $this->temporaryFiles[] = $path;

        $this->expectException(FileReadException::class);
        MatrixFactory::createFromUploadedFile(new UploadedFile($path, 'file.' . $extension, null, null, true));
    }

    public static function unreadableFileProvider(): Generator
    {
        yield ['xlsx'];
        yield ['ods'];
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
        $this->temporaryFiles = [];
    }

    private function createCsvFile(string $content): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'bei');
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, 'file.csv', null, null, true);
    }

    /**
     * @throws SpreadsheetException
     * @throws WriterException
     */
    private function createFile(string $fileExtension, CsvDelimiterEnum $delimiter, array $data = []): UploadedFile
    {
        $fileExtension = strtolower($fileExtension);
        $filename = 'file.' . $fileExtension;

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($data);

        $writer = IOFactory::createWriter($spreadsheet, ucfirst($fileExtension));
        if ($writer instanceof Csv) {
            $writer->setDelimiter($delimiter->value);
        }
        $writer->save($filename);

        return new UploadedFile($filename, $filename);
    }
}

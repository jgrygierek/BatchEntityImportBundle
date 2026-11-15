<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Service;

use Generator;
use JG\BatchEntityImportBundle\Enums\CsvDelimiterEnum;
use JG\BatchEntityImportBundle\Service\CsvDelimiterDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvDelimiterDetectorTest extends TestCase
{
    #[DataProvider('contentProvider')]
    public function testDetect(string $content, CsvDelimiterEnum $expectedDelimiter): void
    {
        self::assertSame($expectedDelimiter, new CsvDelimiterDetector()->detect($content));
    }

    public static function contentProvider(): Generator
    {
        yield 'comma' => ["name,description\nJan,abc", CsvDelimiterEnum::COMMA];
        yield 'semicolon' => ["name;description\nJan;abc", CsvDelimiterEnum::SEMICOLON];
        yield 'empty content' => ['', CsvDelimiterEnum::SEMICOLON];
        yield 'single column' => ["name\nJan", CsvDelimiterEnum::SEMICOLON];
        yield 'other delimiter in records' => ["name;description\nJan;\"a, b, c, d\"\nEwa;e, f, g", CsvDelimiterEnum::SEMICOLON];
        yield 'other delimiter in quoted header' => ["\"name, surname\";description;age\nJan;abc;5", CsvDelimiterEnum::SEMICOLON];
        yield 'new line in quoted header' => ["\"name\n;;;\",description\nJan,abc", CsvDelimiterEnum::COMMA];
        yield 'escaped quote in header' => ["\"name \"\"x\"\", ,\";description\nJan;abc", CsvDelimiterEnum::SEMICOLON];
        yield 'windows new lines' => ["name,description\r\nJan;abc;def;ghi", CsvDelimiterEnum::COMMA];
    }
}

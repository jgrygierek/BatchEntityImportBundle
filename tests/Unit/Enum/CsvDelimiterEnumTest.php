<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Enum;

use JG\BatchEntityImportBundle\Enum\CsvDelimiterEnum;
use PHPUnit\Framework\TestCase;

class CsvDelimiterEnumTest extends TestCase
{
    public function testEnum(): void
    {
        $this->assertCount(2, CsvDelimiterEnum::cases());
        $this->assertSame(';', CsvDelimiterEnum::SEMICOLON->value);
        $this->assertSame(',', CsvDelimiterEnum::COMMA->value);
    }
}

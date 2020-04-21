<?php

namespace JG\BatchEntityImportBundle\Tests\Utils;

use Generator;
use JG\BatchEntityImportBundle\Utils\StringHelper;
use PHPUnit\Framework\TestCase;

class StringHelperTest extends TestCase
{
    /**
     * @dataProvider pascalCaseDataProvider
     *
     * @param string $underscoreString
     * @param string $expected
     */
    public function testToPascalCaseSuccess(string $underscoreString, string $expected): void
    {
        $this->assertEquals($expected, StringHelper::underscoreToPascalCase($underscoreString));
    }

    public function pascalCaseDataProvider(): Generator
    {
        yield ['aa_bb_cc', 'AaBbCc'];
        yield ['aa_bB_cc', 'AaBBCc'];
        yield ['aa bb cc', 'AaBbCc'];
        yield ['AaBbCc', 'AaBbCc'];
        yield ['', ''];
    }

    /**
     * @dataProvider camelCaseDataProvider
     *
     * @param string $underscoreString
     * @param string $expected
     */
    public function testToCamelCaseSuccess(string $underscoreString, string $expected): void
    {
        $this->assertEquals($expected, StringHelper::underscoreToCamelCase($underscoreString));
    }

    public function camelCaseDataProvider(): Generator
    {
        yield ['aa_bb_cc', 'aaBbCc'];
        yield ['aa_bB_cc', 'aaBBCc'];
        yield ['aa bb cc', 'aaBbCc'];
        yield ['AaBbCc', 'aaBbCc'];
        yield ['', ''];
    }
}

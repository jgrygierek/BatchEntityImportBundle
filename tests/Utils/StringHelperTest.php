<?php

namespace JG\BatchEntityImportBundle\Tests\Utils;

use Generator;
use JG\BatchEntityImportBundle\Utils\StringHelper;
use PHPUnit\Framework\TestCase;

class StringHelperTest extends TestCase
{
    /**
     * @dataProvider dataProvider
     *
     * @param string $underscoreString
     * @param string $expected
     */
    public function testSuccess(string $underscoreString, string $expected): void
    {
        $this->assertEquals($expected, StringHelper::underscoreToPascalCase($underscoreString));
    }

    public function dataProvider(): Generator
    {
        yield ['aa_bb_cc', 'AaBbCc'];
        yield ['aa_bB_cc', 'AaBBCc'];
        yield ['aa bb cc', 'AaBbCc'];
        yield ['AaBbCc', 'AaBbCc'];
        yield ['', ''];
    }
}

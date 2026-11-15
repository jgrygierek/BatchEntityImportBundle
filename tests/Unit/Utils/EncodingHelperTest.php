<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Utils;

use Generator;
use JG\BatchEntityImportBundle\Utils\EncodingHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EncodingHelperTest extends TestCase
{
    #[DataProvider('encodingProvider')]
    public function testIsSupported(string $encoding, bool $expected): void
    {
        self::assertSame($expected, EncodingHelper::isSupported($encoding));
    }

    public function testErrorHandlerIsRestored(): void
    {
        $handler = static fn (): bool => true;
        set_error_handler($handler);

        try {
            EncodingHelper::isSupported('CP125O');
            EncodingHelper::isSupported('CP1250');

            // returns handler which was set before this call
            self::assertSame($handler, set_error_handler(null));
            restore_error_handler();
        } finally {
            restore_error_handler();
        }
    }

    public static function encodingProvider(): Generator
    {
        yield ['UTF-8', true];
        yield ['CP1250', true];
        yield ['cp1250', true];
        yield ['Windows-1250', true];
        yield ['ISO-8859-2', true];
        yield ['CP1251', true];
        yield ['CP1252', true];
        yield ['Shift_JIS', true];
        yield ['EUC-KR', true];
        yield ['GBK', true];
        yield ['BIG5', true];
        // single byte is not a valid character in these encodings
        yield ['UTF-16', true];
        yield ['UTF-16LE', true];
        yield ['UTF-32BE', true];
        yield ['CP125O', false];
        yield ['unknown', false];
        yield ['', false];
    }
}

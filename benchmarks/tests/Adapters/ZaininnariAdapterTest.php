<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Tests\Adapters;

use Akankov\HtmlMinBench\Adapters\ZaininnariAdapter;
use PHPUnit\Framework\TestCase;

final class ZaininnariAdapterTest extends TestCase
{
    public function testNameIsStable(): void
    {
        self::assertSame('zaininnari/html-minifier', (new ZaininnariAdapter())->name());
    }

    public function testVersionIsNonEmpty(): void
    {
        self::assertNotSame('', (new ZaininnariAdapter())->version());
    }

    public function testIsNotUnsafe(): void
    {
        self::assertFalse((new ZaininnariAdapter())->isUnsafeReference());
    }

    public function testMinifyCompresses(): void
    {
        $input  = "<html>\n  <body>\n    <p>hi</p>\n  </body>\n</html>";
        $output = (new ZaininnariAdapter())->minify($input);
        self::assertLessThan(\strlen($input), \strlen($output));
        self::assertStringNotContainsString("\n  ", $output, 'expected whitespace collapsed');
        self::assertStringContainsString('<p>hi</p>', $output, 'expected tag content preserved');
    }
}

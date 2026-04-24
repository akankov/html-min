<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Tests\Adapters;

use Akankov\HtmlMinBench\Adapters\WyrihaximusAdapter;
use PHPUnit\Framework\TestCase;

final class WyrihaximusAdapterTest extends TestCase
{
    public function testNameIsStable(): void
    {
        self::assertSame('wyrihaximus/html-compress', (new WyrihaximusAdapter())->name());
    }

    public function testVersionIsNonEmpty(): void
    {
        self::assertNotSame('', (new WyrihaximusAdapter())->version());
    }

    public function testIsNotUnsafe(): void
    {
        self::assertFalse((new WyrihaximusAdapter())->isUnsafeReference());
    }

    public function testMinifyCompresses(): void
    {
        $input  = "<html>\n  <body>\n    <p>hi</p>\n  </body>\n</html>";
        $output = (new WyrihaximusAdapter())->minify($input);
        self::assertLessThan(\strlen($input), \strlen($output));
        self::assertStringNotContainsString("\n  ", $output, 'expected whitespace collapsed');
        self::assertStringContainsString('<p>hi', $output, 'expected tag content preserved');
    }
}

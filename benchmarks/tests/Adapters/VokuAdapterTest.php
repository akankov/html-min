<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Tests\Adapters;

use Akankov\HtmlMinBench\Adapters\VokuAdapter;
use PHPUnit\Framework\TestCase;

final class VokuAdapterTest extends TestCase
{
    public function testNameIsStable(): void
    {
        self::assertSame('voku/html-min', (new VokuAdapter())->name());
    }

    public function testVersionIsNonEmpty(): void
    {
        self::assertNotSame('', (new VokuAdapter())->version());
    }

    public function testIsNotUnsafe(): void
    {
        self::assertFalse((new VokuAdapter())->isUnsafeReference());
    }

    public function testMinifyCompresses(): void
    {
        $input  = "<html>\n  <body>\n    <p>hi</p>\n  </body>\n</html>";
        $output = (new VokuAdapter())->minify($input);
        self::assertLessThan(strlen($input), strlen($output));
        self::assertStringNotContainsString("\n  ", $output, 'expected whitespace collapsed');
        self::assertStringContainsString('<p>hi', $output, 'expected tag content preserved');
    }
}

<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Tests\Adapters;

use Akankov\HtmlMinBench\Adapters\AkankovAdapter;
use PHPUnit\Framework\TestCase;

final class AkankovAdapterTest extends TestCase
{
    public function testNameIsStable(): void
    {
        self::assertSame('akankov/html-min', (new AkankovAdapter())->name());
    }

    public function testVersionIsNonEmpty(): void
    {
        self::assertNotSame('', (new AkankovAdapter())->version());
    }

    public function testIsNotUnsafe(): void
    {
        self::assertFalse((new AkankovAdapter())->isUnsafeReference());
    }

    public function testMinifyCompresses(): void
    {
        $input  = "<html>\n  <body>\n    <p>hi</p>\n  </body>\n</html>";
        $output = (new AkankovAdapter())->minify($input);
        self::assertLessThan(strlen($input), strlen($output));
        self::assertStringNotContainsString("\n  ", $output, 'expected whitespace collapsed');
        self::assertStringContainsString('<p>hi', $output, 'expected tag content preserved');
    }
}

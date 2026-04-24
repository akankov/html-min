<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Tests\Adapters;

use Akankov\HtmlMinBench\Adapters\AbordageAdapter;
use PHPUnit\Framework\TestCase;

final class AbordageAdapterTest extends TestCase
{
    public function testNameIsStable(): void
    {
        self::assertSame('abordage/html-min', (new AbordageAdapter())->name());
    }

    public function testIsLabelledUnsafe(): void
    {
        self::assertTrue((new AbordageAdapter())->isUnsafeReference());
    }

    public function testVersionIsNonEmpty(): void
    {
        self::assertNotSame('', (new AbordageAdapter())->version());
    }

    public function testMinifyCompresses(): void
    {
        // Abordage's default config requires <!DOCTYPE within the first 100 bytes
        // or it short-circuits and returns the input verbatim.
        $input  = "<!DOCTYPE html>\n<html>\n  <body>\n    <p>hi</p>\n  </body>\n</html>";
        $output = (new AbordageAdapter())->minify($input);
        self::assertLessThan(strlen($input), strlen($output));
        self::assertStringNotContainsString("\n  ", $output, 'expected whitespace collapsed');
        self::assertStringContainsString('hi', $output, 'expected content preserved');
    }
}

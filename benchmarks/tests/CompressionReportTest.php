<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Tests;

use Akankov\HtmlMinBench\Adapters\AkankovAdapter;
use Akankov\HtmlMinBench\Adapters\MinifierAdapter;
use Akankov\HtmlMinBench\Bench\CompressionReport;
use PHPUnit\Framework\TestCase;

final class CompressionReportTest extends TestCase
{
    public function testMeasureRecordsInputAndOutputBytes(): void
    {
        $adapter = new AkankovAdapter();
        $html    = "<html>\n  <body>\n    <p>hi</p>\n  </body>\n</html>";
        $row     = CompressionReport::measure($adapter, 'fixture-name', $html);

        self::assertSame('akankov/html-min', $row['adapter']);
        self::assertSame('fixture-name', $row['fixture']);
        self::assertSame(\strlen($html), $row['input_bytes']);
        self::assertGreaterThan(0, $row['output_bytes']);
        self::assertLessThan($row['input_bytes'], $row['output_bytes']);
        self::assertGreaterThan(0, $row['output_gzipped_bytes']);
        self::assertGreaterThan(0, $row['input_gzipped_bytes']);
        self::assertGreaterThanOrEqual(0.0, $row['ratio_raw']);
        self::assertLessThanOrEqual(1.0, $row['ratio_raw']);
        self::assertSame(64, \strlen($row['sha256_out']));
        self::assertTrue($row['parses_ok']);
    }

    public function testMeasureMarksFailureWhenOutputEmpty(): void
    {
        $adapter = new class () implements MinifierAdapter {
            public function name(): string
            {
                return 'akankov/html-min';
            }
            public function version(): string
            {
                return 'unknown';
            }
            public function minify(string $html): string
            {
                return '';
            }
            public function isUnsafeReference(): bool
            {
                return false;
            }
        };
        $row = CompressionReport::measure($adapter, 'x', '<p>hi</p>');
        self::assertFalse($row['parses_ok']);
        self::assertSame(0, $row['output_bytes']);
    }
}

<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Tests\Report;

use Akankov\HtmlMinBench\Report\ReportRenderer;
use PHPUnit\Framework\TestCase;

final class ReportRendererTest extends TestCase
{
    /**
     * @return array{
     *     header: array{
     *         generated_at:string,
     *         php_version:string,
     *         git_sha:string,
     *         host:string,
     *         adapters:list<array{name:string,version:string,unsafe:bool}>
     *     },
     *     speed: list<array{adapter:string, fixture:string, ms_per_op:float, stddev:float, peak_memory_mb:float}>,
     *     compression: list<array{adapter:string, fixture:string, ratio_raw:float, ratio_gz:float, parses_ok:bool}>
     * }
     */
    private function fixtureInput(): array
    {
        return [
            'header' => [
                'generated_at' => '2026-04-23T10:00:00+00:00',
                'php_version'  => '8.3.0',
                'git_sha'      => 'abc1234',
                'host'         => 'Darwin test 25.4',
                'adapters'     => [
                    ['name' => 'akankov/html-min',     'version' => '1.0.0', 'unsafe' => false],
                    ['name' => 'abordage/html-min',    'version' => '1.0.0', 'unsafe' => true],
                ],
            ],
            'speed' => [
                ['adapter' => 'akankov/html-min',  'fixture' => 'wiki', 'ms_per_op' => 12.3, 'stddev' => 0.5, 'peak_memory_mb' => 6.2],
                ['adapter' => 'abordage/html-min', 'fixture' => 'wiki', 'ms_per_op' => 3.1,  'stddev' => 0.2, 'peak_memory_mb' => 4.8],
            ],
            'compression' => [
                ['adapter' => 'akankov/html-min',  'fixture' => 'wiki', 'ratio_raw' => 0.75, 'ratio_gz' => 0.85, 'parses_ok' => true],
                ['adapter' => 'abordage/html-min', 'fixture' => 'wiki', 'ratio_raw' => 0.80, 'ratio_gz' => 0.90, 'parses_ok' => true],
            ],
        ];
    }

    public function testRenderIncludesHeader(): void
    {
        $md = ReportRenderer::render($this->fixtureInput());
        self::assertStringContainsString('Generated: 2026-04-23T10:00:00+00:00', $md);
        self::assertStringContainsString('PHP 8.3.0', $md);
        self::assertStringContainsString('abc1234', $md);
        self::assertStringNotContainsString('dirty', $md);
    }

    public function testDirtyWorkingTreeIsLabelledOnSha(): void
    {
        $data = $this->fixtureInput();
        $data['header']['git_dirty'] = true;
        $md = ReportRenderer::render($data);
        self::assertStringContainsString('abc1234 (dirty: based on uncommitted source)', $md);
    }

    public function testRenderIncludesSpeedAndCompressionTables(): void
    {
        $md = ReportRenderer::render($this->fixtureInput());
        self::assertStringContainsString('## Summary', $md);
        self::assertStringContainsString('## Speed', $md);
        self::assertStringContainsString('## Peak Memory', $md);
        self::assertStringContainsString('## Compression', $md);
        self::assertStringContainsString('akankov/html-min', $md);
        self::assertStringContainsString('abordage/html-min', $md);
    }

    public function testSummaryTableReportsMedianGeomeanFailuresAndAvgRatio(): void
    {
        $data = [
            'header' => [
                'generated_at' => '2026-04-23T10:00:00+00:00',
                'php_version'  => '8.3.0',
                'git_sha'      => 'abc1234',
                'host'         => 'Darwin test 25.4',
                'adapters'     => [
                    ['name' => 'fast', 'version' => '1', 'unsafe' => false],
                    ['name' => 'slow-with-failure', 'version' => '1', 'unsafe' => false],
                ],
            ],
            'speed' => [
                ['adapter' => 'fast',              'fixture' => 'a', 'ms_per_op' => 4.0,  'stddev' => 0.0, 'peak_memory_mb' => 1.0],
                ['adapter' => 'fast',              'fixture' => 'b', 'ms_per_op' => 16.0, 'stddev' => 0.0, 'peak_memory_mb' => 1.0],
                ['adapter' => 'slow-with-failure', 'fixture' => 'a', 'ms_per_op' => 20.0, 'stddev' => 0.0, 'peak_memory_mb' => 1.0],
                ['adapter' => 'slow-with-failure', 'fixture' => 'b', 'ms_per_op' => 25.0, 'stddev' => 0.0, 'peak_memory_mb' => 1.0],
            ],
            'compression' => [
                ['adapter' => 'fast',              'fixture' => 'a', 'ratio_raw' => 0.80, 'ratio_gz' => 0.85, 'parses_ok' => true],
                ['adapter' => 'fast',              'fixture' => 'b', 'ratio_raw' => 0.80, 'ratio_gz' => 0.85, 'parses_ok' => true],
                ['adapter' => 'slow-with-failure', 'fixture' => 'a', 'ratio_raw' => 0.80, 'ratio_gz' => 0.95, 'parses_ok' => true],
                ['adapter' => 'slow-with-failure', 'fixture' => 'b', 'ratio_raw' => 0.10, 'ratio_gz' => 0.20, 'parses_ok' => false],
            ],
        ];
        $md = ReportRenderer::render($data);

        self::assertStringContainsString('## Summary', $md);
        // fast: median(4, 16) = 10.0; geomean = sqrt(64) = 8.0; 0/2 failures; avg ratio 85.0%
        self::assertStringContainsString('| fast | **10.0** | **8.0** | 0 / 2 | **85.0%** |', $md);
        // slow-with-failure: median of (20.0) only = 20.0; geomean = 20.0; 1/2 failures; avg ratio 95.0%
        self::assertStringContainsString('| slow-with-failure | 20.0 | 20.0 | 1 / 2 | 95.0% |', $md);
    }

    public function testUnsafeAdapterIsLabelled(): void
    {
        $md = ReportRenderer::render($this->fixtureInput());
        self::assertStringContainsString('regex-based (unsafe reference)', $md);
        self::assertStringContainsString('abordage/html-min', $md);
    }

    public function testFastestCellIsBold(): void
    {
        $md = ReportRenderer::render($this->fixtureInput());
        self::assertMatchesRegularExpression('/\*\*3\.1\s*±/', $md);
    }

    public function testLowestMemoryCellIsBold(): void
    {
        $md = ReportRenderer::render($this->fixtureInput());
        self::assertStringContainsString('**4.8 MiB**', $md);
    }

    public function testBrokenAdapterShowsNaInSpeedAndMemoryTables(): void
    {
        $data = [
            'header' => [
                'generated_at' => '2026-04-23T10:00:00+00:00',
                'php_version'  => '8.3.0',
                'git_sha'      => 'abc1234',
                'host'         => 'Darwin test 25.4',
                'adapters'     => [
                    ['name' => 'broken-fast', 'version' => '1', 'unsafe' => false],
                    ['name' => 'slow-but-correct', 'version' => '1', 'unsafe' => false],
                ],
            ],
            'speed' => [
                ['adapter' => 'broken-fast',     'fixture' => 'f1', 'ms_per_op' => 0.1, 'stddev' => 0.0, 'peak_memory_mb' => 1.0],
                ['adapter' => 'slow-but-correct', 'fixture' => 'f1', 'ms_per_op' => 8.5, 'stddev' => 0.1, 'peak_memory_mb' => 5.0],
            ],
            'compression' => [
                ['adapter' => 'broken-fast',      'fixture' => 'f1', 'ratio_raw' => 0.10, 'ratio_gz' => 0.20, 'parses_ok' => false],
                ['adapter' => 'slow-but-correct', 'fixture' => 'f1', 'ratio_raw' => 0.50, 'ratio_gz' => 0.60, 'parses_ok' => true],
            ],
        ];
        $md = ReportRenderer::render($data);
        // broken adapter must NOT appear with its 0.1ms timing as fastest
        self::assertStringNotContainsString('**0.1', $md);
        self::assertStringNotContainsString('**1.0 MiB**', $md);
        // slow-but-correct must be the only "best" cell — bolded
        self::assertMatchesRegularExpression('/\*\*8\.5\s*±/', $md);
        self::assertStringContainsString('**5.0 MiB**', $md);
    }

    public function testParseFailureIsNotMarkedBest(): void
    {
        $data = [
            'header' => [
                'generated_at' => '2026-04-23T10:00:00+00:00',
                'php_version'  => '8.3.0',
                'git_sha'      => 'abc1234',
                'host'         => 'Darwin test 25.4',
                'adapters'     => [
                    ['name' => 'adapter-a', 'version' => '1', 'unsafe' => false],
                    ['name' => 'adapter-b', 'version' => '1', 'unsafe' => false],
                ],
            ],
            'speed'       => [],
            'compression' => [
                // adapter-a: low ratio but parses_ok=false → should NOT be marked best
                ['adapter' => 'adapter-a', 'fixture' => 'f1', 'ratio_raw' => 0.10, 'ratio_gz' => 0.20, 'parses_ok' => false],
                // adapter-b: higher ratio but parse-valid → should BE marked best
                ['adapter' => 'adapter-b', 'fixture' => 'f1', 'ratio_raw' => 0.50, 'ratio_gz' => 0.60, 'parses_ok' => true],
            ],
        ];
        $md = ReportRenderer::render($data);
        // adapter-a's cell is n/a† and must NOT be wrapped in **...**
        self::assertStringNotContainsString('**n/a†**', $md);
        // adapter-b's cell should be bolded (it's the only parse-valid row)
        self::assertMatchesRegularExpression('/\*\*60\.0%/', $md);
    }
}

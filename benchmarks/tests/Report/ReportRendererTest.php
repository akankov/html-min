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
     *     speed: list<array{adapter:string, fixture:string, ms_per_op:float, stddev:float}>,
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
                ['adapter' => 'akankov/html-min',  'fixture' => 'wiki', 'ms_per_op' => 12.3, 'stddev' => 0.5],
                ['adapter' => 'abordage/html-min', 'fixture' => 'wiki', 'ms_per_op' => 3.1,  'stddev' => 0.2],
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
    }

    public function testRenderIncludesSpeedAndCompressionTables(): void
    {
        $md = ReportRenderer::render($this->fixtureInput());
        self::assertStringContainsString('## Speed', $md);
        self::assertStringContainsString('## Compression', $md);
        self::assertStringContainsString('akankov/html-min', $md);
        self::assertStringContainsString('abordage/html-min', $md);
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

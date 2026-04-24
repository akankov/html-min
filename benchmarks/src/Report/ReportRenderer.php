<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Report;

final class ReportRenderer
{
    /**
     * @param array{
     *     header: array{generated_at:string, php_version:string, git_sha:string, host:string, adapters:list<array{name:string,version:string,unsafe:bool}>},
     *     speed: list<array{adapter:string, fixture:string, ms_per_op:float, stddev:float}>,
     *     compression: list<array{adapter:string, fixture:string, ratio_raw:float, ratio_gz:float, parses_ok:bool}>
     * } $data
     */
    public static function render(array $data): string
    {
        $out  = "# html-min benchmarks\n\n";
        $out .= self::header($data['header']);
        $out .= "## Speed (ms/op, lower is better)\n\n";
        $out .= self::speedTable($data['speed'], $data['header']['adapters']);
        $out .= "\n## Compression (gzipped ratio, lower is better)\n\n";
        $out .= self::compressionTable($data['compression'], $data['header']['adapters']);
        $out .= "\n" . self::methodology($data['header']['adapters']);
        return $out;
    }

    private static function header(array $h): string
    {
        $lines  = "Generated: {$h['generated_at']}\n";
        $lines .= "Host: {$h['host']} / PHP {$h['php_version']} / git {$h['git_sha']}\n\n";
        $lines .= "**Adapter versions:**\n";
        foreach ($h['adapters'] as $a) {
            $tag = $a['unsafe'] ? ' _(regex-based, unsafe reference)_' : '';
            $lines .= "- `{$a['name']}` {$a['version']}{$tag}\n";
        }
        return $lines . "\n";
    }

    private static function speedTable(array $rows, array $adapters): string
    {
        return self::renderTable(
            rows: $rows,
            adapters: $adapters,
            valueKey: 'ms_per_op',
            formatCell: fn (array $r) => sprintf('%.1f ± %.1f', $r['ms_per_op'], $r['stddev']),
        );
    }

    private static function compressionTable(array $rows, array $adapters): string
    {
        return self::renderTable(
            rows: $rows,
            adapters: $adapters,
            valueKey: 'ratio_gz',
            formatCell: fn (array $r) => $r['parses_ok']
                ? sprintf('%.1f%% (raw %.1f%%)', $r['ratio_gz'] * 100, $r['ratio_raw'] * 100)
                : 'n/a†',
        );
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name:string,version:string,unsafe:bool}> $adapters
     * @param callable(array<string,mixed>):string $formatCell
     */
    private static function renderTable(array $rows, array $adapters, string $valueKey, callable $formatCell): string
    {
        $fixtures = [];
        foreach ($rows as $r) {
            $fixtures[$r['fixture']] = true;
        }
        $fixtures = array_keys($fixtures);

        $grid = [];
        foreach ($rows as $r) {
            $grid[$r['adapter']][$r['fixture']] = $r;
        }

        $header = '| adapter | ' . implode(' | ', $fixtures) . " |\n";
        $header .= '|---' . str_repeat('|---', count($fixtures)) . "|\n";

        $body = '';
        foreach ($adapters as $a) {
            $cells = [];
            foreach ($fixtures as $f) {
                $row = $grid[$a['name']][$f] ?? null;
                if ($row === null) {
                    $cells[] = '—';
                    continue;
                }
                $val = $formatCell($row);
                if (self::isBestValue($grid, $a['name'], $f, $valueKey)) {
                    $val = "**$val**";
                }
                $cells[] = $val;
            }
            $label = $a['unsafe'] ? "{$a['name']} †" : $a['name'];
            $body .= "| $label | " . implode(' | ', $cells) . " |\n";
        }
        return $header . $body;
    }

    private static function isBestValue(array $grid, string $adapterName, string $fixture, string $valueKey): bool
    {
        $ownRow = $grid[$adapterName][$fixture] ?? null;
        if ($ownRow === null) {
            return false;
        }
        // A row that failed its parse check cannot be "best" — the formatter
        // renders it as n/a†, and bolding broken output would claim a win for
        // invalid HTML.
        if (($ownRow['parses_ok'] ?? true) === false) {
            return false;
        }
        $own = $ownRow[$valueKey] ?? null;
        if ($own === null) {
            return false;
        }
        foreach ($grid as $byFixture) {
            $other = $byFixture[$fixture] ?? null;
            if ($other === null) {
                continue;
            }
            if (($other['parses_ok'] ?? true) === false) {
                continue; // broken rows don't disqualify valid ones
            }
            $v = $other[$valueKey] ?? null;
            if ($v !== null && $v < $own) {
                return false;
            }
        }
        return true;
    }

    private static function methodology(array $adapters): string
    {
        $unsafe = array_values(array_filter($adapters, fn ($a) => $a['unsafe']));
        $out  = "## Methodology\n\n";
        $out .= "- Default configuration for every adapter. No per-adapter tuning.\n";
        $out .= "- Same input bytes. UTF-8 throughout.\n";
        $out .= "- Single-threaded, single-process PHP.\n";
        $out .= "- No forced GC between runs (PHPBench default).\n";
        $out .= "- Speed measured via PHPBench: 1 warmup revolution, 10 revolutions × 5 iterations.\n";
        $out .= "- Compression measured separately by running each adapter once per fixture and measuring output via `gzencode(\$out, 9)`.\n";
        $out .= "- Every output is round-tripped through `DOMDocument::loadHTML`; cells marked `n/a†` failed this check.\n";
        if ($unsafe !== []) {
            $names = implode(', ', array_map(fn ($a) => "`{$a['name']}`", $unsafe));
            $out .= "- † marks adapters flagged as **regex-based (unsafe reference)**: $names. Their speed numbers are informative but the comparison class is asymmetric — they skip structural HTML parsing.\n";
        }
        $out .= "\n## Non-claims\n\n";
        $out .= "- Not a correctness judgement beyond DOM round-trip parseability.\n";
        $out .= "- Results are for this corpus on this host. Ratios between adapters are the meaningful signal.\n";
        return $out;
    }
}

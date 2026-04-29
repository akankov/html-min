<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Report;

/**
 * @phpstan-type AdapterMeta array{name:string, version:string, unsafe:bool}
 * @phpstan-type HeaderShape array{
 *     generated_at:string,
 *     php_version:string,
 *     git_sha:string,
 *     git_dirty?:bool,
 *     host:string,
 *     adapters:list<AdapterMeta>
 * }
 * @phpstan-type SpeedRow array{adapter:string, fixture:string, ms_per_op:float, stddev:float, peak_memory_mb:float}
 * @phpstan-type CompressionRow array{adapter:string, fixture:string, ratio_raw:float, ratio_gz:float, parses_ok:bool}
 * @phpstan-type ReportData array{
 *     header: HeaderShape,
 *     speed: list<SpeedRow>,
 *     compression: list<CompressionRow>
 * }
 */
final class ReportRenderer
{
    /**
     * @param ReportData $data
     */
    public static function render(array $data): string
    {
        $parsesOk = self::parsesOkMap($data['compression']);

        $out  = "# html-min benchmarks\n\n";
        $out .= self::header($data['header']);
        $out .= "## Summary\n\n";
        $out .= self::summaryTable($data['speed'], $data['compression'], $data['header']['adapters'], $parsesOk);
        $out .= "\n## Speed (ms/op, lower is better)\n\n";
        $out .= self::speedTable($data['speed'], $data['header']['adapters'], $parsesOk);
        $out .= "\n## Peak Memory (MiB, lower is better)\n\n";
        $out .= self::memoryTable($data['speed'], $data['header']['adapters'], $parsesOk);
        $out .= "\n## Compression (gzipped ratio, lower is better)\n\n";
        $out .= self::compressionTable($data['compression'], $data['header']['adapters']);
        return $out . ("\n" . self::methodology($data['header']['adapters']));
    }

    /**
     * @param list<SpeedRow> $speed
     * @param list<CompressionRow> $compression
     * @param list<AdapterMeta> $adapters
     * @param array<string, array<string, bool>> $parsesOk
     */
    private static function summaryTable(array $speed, array $compression, array $adapters, array $parsesOk): string
    {
        $fixtures = self::fixturesOf($speed);
        $totalFixtures = \count($fixtures);

        /** @var array<string, list<float>> $okSpeedByAdapter */
        $okSpeedByAdapter = [];
        foreach ($speed as $r) {
            if (($parsesOk[$r['adapter']][$r['fixture']] ?? true) === false) {
                continue;
            }
            $okSpeedByAdapter[$r['adapter']][] = $r['ms_per_op'];
        }

        /** @var array<string, list<float>> $okRatioByAdapter */
        $okRatioByAdapter = [];
        /** @var array<string, int> $failuresByAdapter */
        $failuresByAdapter = [];
        foreach ($compression as $r) {
            if ($r['parses_ok']) {
                $okRatioByAdapter[$r['adapter']][] = $r['ratio_gz'];
            } else {
                $failuresByAdapter[$r['adapter']] = ($failuresByAdapter[$r['adapter']] ?? 0) + 1;
            }
        }

        $stats = [];
        foreach ($adapters as $a) {
            $name = $a['name'];
            $okSpeed = $okSpeedByAdapter[$name] ?? [];
            $okRatio = $okRatioByAdapter[$name] ?? [];
            $stats[$name] = [
                'median'   => $okSpeed === [] ? null : self::median($okSpeed),
                'geomean'  => $okSpeed === [] ? null : self::geomean($okSpeed),
                'failures' => $failuresByAdapter[$name] ?? 0,
                'avgRatio' => $okRatio === [] ? null : array_sum($okRatio) / \count($okRatio),
            ];
        }

        $bestMedian   = self::lowestNonNull(array_column($stats, 'median'));
        $bestGeomean  = self::lowestNonNull(array_column($stats, 'geomean'));
        $bestAvgRatio = self::lowestNonNull(array_column($stats, 'avgRatio'));

        $out  = "| adapter | median ms/op | geomean ms/op | parse failures | avg gzipped ratio |\n";
        $out .= "|---|---|---|---|---|\n";
        foreach ($adapters as $a) {
            $name  = $a['name'];
            $label = $a['unsafe'] ? "{$name} †" : $name;
            $s     = $stats[$name];
            $median   = self::formatStat($s['median'], '%.1f', $s['median'] === $bestMedian);
            $geomean  = self::formatStat($s['geomean'], '%.1f', $s['geomean'] === $bestGeomean);
            $failures = $s['failures'] . ' / ' . $totalFixtures;
            $avgRatio = self::formatStat(
                $s['avgRatio'] === null ? null : $s['avgRatio'] * 100,
                '%.1f%%',
                $s['avgRatio'] === $bestAvgRatio,
            );
            $out .= "| {$label} | {$median} | {$geomean} | {$failures} | {$avgRatio} |\n";
        }
        return $out;
    }

    /**
     * @param list<float> $values
     */
    private static function median(array $values): float
    {
        sort($values);
        $n = \count($values);
        if ($n === 0) {
            return 0.0;
        }
        $mid = (int) ($n / 2);
        return $n % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /**
     * @param list<float> $values
     */
    private static function geomean(array $values): float
    {
        $n = \count($values);
        if ($n === 0) {
            return 0.0;
        }
        $sumLog = 0.0;
        foreach ($values as $v) {
            if ($v <= 0) {
                return 0.0; // log of non-positive is undefined; bail with 0.
            }
            $sumLog += log($v);
        }
        return exp($sumLog / $n);
    }

    /**
     * @param list<float|null> $values
     */
    private static function lowestNonNull(array $values): ?float
    {
        $filtered = array_filter($values, static fn (?float $v): bool => $v !== null);
        return $filtered === [] ? null : min($filtered);
    }

    private static function formatStat(?float $value, string $fmt, bool $isBest): string
    {
        if ($value === null) {
            return '—';
        }
        $cell = \sprintf($fmt, $value);
        return $isBest ? "**{$cell}**" : $cell;
    }

    /**
     * @param list<CompressionRow> $rows
     *
     * @return array<string, array<string, bool>>
     */
    private static function parsesOkMap(array $rows): array
    {
        /** @var array<string, array<string, bool>> $map */
        $map = [];
        foreach ($rows as $r) {
            $map[$r['adapter']][$r['fixture']] = $r['parses_ok'];
        }
        return $map;
    }

    /**
     * @param HeaderShape $h
     */
    private static function header(array $h): string
    {
        $sha = $h['git_sha'];
        if (($h['git_dirty'] ?? false) === true) {
            $sha .= ' (dirty: based on uncommitted source)';
        }
        $lines  = "Generated: {$h['generated_at']}\n";
        $lines .= "Host: {$h['host']} / PHP {$h['php_version']} / git {$sha}\n\n";
        $lines .= "**Adapter versions:**\n";
        foreach ($h['adapters'] as $a) {
            $tag = $a['unsafe'] ? ' _(regex-based, unsafe reference)_' : '';
            $lines .= "- `{$a['name']}` {$a['version']}{$tag}\n";
        }
        return $lines . "\n";
    }

    /**
     * @param list<SpeedRow> $rows
     * @param list<AdapterMeta> $adapters
     * @param array<string, array<string, bool>> $parsesOk
     */
    private static function speedTable(array $rows, array $adapters, array $parsesOk): string
    {
        $fixtures = self::fixturesOf($rows);

        /** @var array<string, array<string, SpeedRow>> $grid */
        $grid = [];
        foreach ($rows as $r) {
            $grid[$r['adapter']][$r['fixture']] = $r;
        }

        $out = self::tableHeader($fixtures);
        foreach ($adapters as $a) {
            $cells = [];
            foreach ($fixtures as $f) {
                $row = $grid[$a['name']][$f] ?? null;
                if ($row === null) {
                    $cells[] = '—';
                    continue;
                }
                if (($parsesOk[$a['name']][$f] ?? true) === false) {
                    $cells[] = 'n/a†';
                    continue;
                }
                $cell = \sprintf('%.1f ± %.1f', $row['ms_per_op'], $row['stddev']);
                if (self::isBestSpeed($grid, $parsesOk, $a['name'], $f, $row['ms_per_op'])) {
                    $cell = "**$cell**";
                }
                $cells[] = $cell;
            }
            $out .= self::renderRow($a, $cells);
        }
        return $out;
    }

    /**
     * @param list<SpeedRow> $rows
     * @param list<AdapterMeta> $adapters
     * @param array<string, array<string, bool>> $parsesOk
     */
    private static function memoryTable(array $rows, array $adapters, array $parsesOk): string
    {
        $fixtures = self::fixturesOf($rows);

        /** @var array<string, array<string, SpeedRow>> $grid */
        $grid = [];
        foreach ($rows as $r) {
            $grid[$r['adapter']][$r['fixture']] = $r;
        }

        $out = self::tableHeader($fixtures);
        foreach ($adapters as $a) {
            $cells = [];
            foreach ($fixtures as $f) {
                $row = $grid[$a['name']][$f] ?? null;
                if ($row === null) {
                    $cells[] = '—';
                    continue;
                }
                if (($parsesOk[$a['name']][$f] ?? true) === false) {
                    $cells[] = 'n/a†';
                    continue;
                }

                $cell = number_format($row['peak_memory_mb'], 1) . ' MiB';
                if (self::isBestMemory($grid, $parsesOk, $a['name'], $f, $row['peak_memory_mb'])) {
                    $cell = "**$cell**";
                }
                $cells[] = $cell;
            }
            $out .= self::renderRow($a, $cells);
        }

        return $out;
    }

    /**
     * @param list<CompressionRow> $rows
     * @param list<AdapterMeta> $adapters
     */
    private static function compressionTable(array $rows, array $adapters): string
    {
        $fixtures = self::fixturesOf($rows);

        /** @var array<string, array<string, CompressionRow>> $grid */
        $grid = [];
        foreach ($rows as $r) {
            $grid[$r['adapter']][$r['fixture']] = $r;
        }

        $out = self::tableHeader($fixtures);
        foreach ($adapters as $a) {
            $cells = [];
            foreach ($fixtures as $f) {
                $row = $grid[$a['name']][$f] ?? null;
                if ($row === null) {
                    $cells[] = '—';
                    continue;
                }
                if (!$row['parses_ok']) {
                    $cells[] = 'n/a†';
                    continue;
                }
                $cell = \sprintf('%.1f%% (raw %.1f%%)', $row['ratio_gz'] * 100, $row['ratio_raw'] * 100);
                if (self::isBestCompression($grid, $a['name'], $f, $row['ratio_gz'])) {
                    $cell = "**$cell**";
                }
                $cells[] = $cell;
            }
            $out .= self::renderRow($a, $cells);
        }
        return $out;
    }

    /**
     * @param list<array{adapter:string, fixture:string}> $rows
     *
     * @return list<string>
     */
    private static function fixturesOf(array $rows): array
    {
        $seen = [];
        foreach ($rows as $r) {
            $seen[$r['fixture']] = true;
        }
        return array_keys($seen);
    }

    /**
     * @param list<string> $fixtures
     */
    private static function tableHeader(array $fixtures): string
    {
        return '| adapter | ' . implode(' | ', $fixtures) . " |\n"
            . '|---' . str_repeat('|---', \count($fixtures)) . "|\n";
    }

    /**
     * @param AdapterMeta $a
     * @param list<string> $cells
     */
    private static function renderRow(array $a, array $cells): string
    {
        $label = $a['unsafe'] ? "{$a['name']} †" : $a['name'];
        return "| $label | " . implode(' | ', $cells) . " |\n";
    }

    /**
     * @param array<string, array<string, SpeedRow>> $grid
     * @param array<string, array<string, bool>> $parsesOk
     */
    private static function isBestSpeed(array $grid, array $parsesOk, string $adapterName, string $fixture, float $ownValue): bool
    {
        unset($grid[$adapterName]);
        foreach ($grid as $otherName => $byFixture) {
            $other = $byFixture[$fixture] ?? null;
            if ($other === null) {
                continue;
            }
            if (($parsesOk[$otherName][$fixture] ?? true) === false) {
                continue; // broken outputs don't get to disqualify valid ones
            }
            if ($other['ms_per_op'] < $ownValue) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string, array<string, SpeedRow>> $grid
     * @param array<string, array<string, bool>> $parsesOk
     */
    private static function isBestMemory(array $grid, array $parsesOk, string $adapterName, string $fixture, float $ownValue): bool
    {
        unset($grid[$adapterName]);
        foreach ($grid as $otherName => $byFixture) {
            $other = $byFixture[$fixture] ?? null;
            if ($other === null) {
                continue;
            }
            if (($parsesOk[$otherName][$fixture] ?? true) === false) {
                continue;
            }
            if ($other['peak_memory_mb'] < $ownValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, array<string, CompressionRow>> $grid
     */
    private static function isBestCompression(array $grid, string $adapterName, string $fixture, float $ownValue): bool
    {
        unset($grid[$adapterName]);
        foreach ($grid as $byFixture) {
            $other = $byFixture[$fixture] ?? null;
            if ($other === null) {
                continue;
            }
            if (!$other['parses_ok']) {
                // broken rows don't disqualify valid ones
                continue;
            }
            if ($other['ratio_gz'] < $ownValue) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param list<AdapterMeta> $adapters
     */
    private static function methodology(array $adapters): string
    {
        $unsafe = array_values(array_filter($adapters, static fn (array $a): bool => $a['unsafe']));
        $out  = "## Methodology\n\n";
        $out .= "- Default configuration for every adapter. No per-adapter tuning.\n";
        $out .= "- Same input bytes. UTF-8 throughout.\n";
        $out .= "- Single-threaded, single-process PHP.\n";
        $out .= "- No forced GC between runs (PHPBench default).\n";
        $out .= "- Speed measured via PHPBench: 1 warmup revolution, 10 revolutions × 5 iterations.\n";
        $out .= "- Peak memory comes from PHPBench's per-iteration `mem-peak`, reported as the max peak resident allocation observed for each (adapter, fixture) case.\n";
        $out .= "- Compression measured separately by running each adapter once per fixture and measuring output via `gzencode(\$out, 9)`.\n";
        $out .= "- Every output is round-tripped through `DOMDocument::loadHTML`; cells marked `n/a†` failed this check.\n";
        if ($unsafe !== []) {
            $names = implode(', ', array_map(static fn (array $a): string => "`{$a['name']}`", $unsafe));
            $out .= "- † marks adapters flagged as **regex-based (unsafe reference)**: $names. Their speed numbers are informative but the comparison class is asymmetric — they skip structural HTML parsing.\n";
        }
        $out .= "\n## Non-claims\n\n";
        $out .= "- Not a correctness judgement beyond DOM round-trip parseability.\n";
        return $out . "- Results are for this corpus on this host. Ratios between adapters are the meaningful signal.\n";
    }
}

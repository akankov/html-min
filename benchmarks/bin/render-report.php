#!/usr/bin/env php
<?php

declare(strict_types=1);

ini_set('display_errors', 'stderr');

require __DIR__ . '/../vendor/autoload.php';

use Akankov\HtmlMinBench\AdapterRegistry;
use Akankov\HtmlMinBench\Report\ReportRenderer;

if ($argc < 4) {
    fwrite(\STDERR, "Usage: render-report.php <phpbench.xml> <compression.json> <out.md>\n");
    exit(1);
}
[$_, $benchPath, $compPath, $outPath] = $argv;

$compRaw = json_decode((string) file_get_contents($compPath), true, flags: \JSON_THROW_ON_ERROR);
if (!is_array($compRaw)) {
    fwrite(\STDERR, "render-report: expected JSON object at $compPath\n");
    exit(3);
}

$generatedAt = is_string($compRaw['generated_at'] ?? null) ? $compRaw['generated_at'] : gmdate('c');
$phpVersion  = is_string($compRaw['php_version'] ?? null) ? $compRaw['php_version'] : \PHP_VERSION;
$rowsRaw     = is_array($compRaw['rows'] ?? null) ? $compRaw['rows'] : [];

// PHPBench --dump-file emits XML. Parse it into the shape ReportRenderer expects.
/** @var list<array{adapter:string, fixture:string, ms_per_op:float, stddev:float}> $speed */
$speed = [];
$xml   = new SimpleXMLElement((string) file_get_contents($benchPath));
foreach ($xml->suite->benchmark as $benchmark) {
    foreach ($benchmark->subject as $subject) {
        foreach ($subject->variant as $variant) {
            $adapter = null;
            $fixture = null;
            foreach ($variant->{'parameter-set'}->parameter as $param) {
                $name  = (string) $param['name'];
                $value = (string) $param['value'];
                if ($name === 'adapter') {
                    $adapter = $value;
                } elseif ($name === 'fixture') {
                    $fixture = $value;
                }
            }
            if ($adapter === null || $fixture === null) {
                continue;
            }
            $stats = $variant->stats;
            if ($stats === null) {
                continue;
            }
            // PHPBench time units default to microseconds; convert to ms.
            $speed[] = [
                'adapter'   => $adapter,
                'fixture'   => $fixture,
                'ms_per_op' => (float) $stats['mean']  / 1000,
                'stddev'    => (float) $stats['stdev'] / 1000,
            ];
        }
    }
}

if ($speed === []) {
    fwrite(\STDERR, "render-report: extracted 0 speed rows from $benchPath — PHPBench dump schema mismatch?\n");
    exit(2);
}

$header = [
    'generated_at' => $generatedAt,
    'php_version'  => $phpVersion,
    'git_sha'      => getenv('BENCH_GIT_SHA') ?: 'unknown',
    'host'         => php_uname('s') . ' ' . php_uname('r'),
    'adapters'     => array_map(
        static fn (\Akankov\HtmlMinBench\Adapters\MinifierAdapter $a): array => ['name' => $a->name(), 'version' => $a->version(), 'unsafe' => $a->isUnsafeReference()],
        AdapterRegistry::all(),
    ),
];

$compressionRows = [];
foreach ($rowsRaw as $r) {
    if (!is_array($r)) {
        continue;
    }
    $adapter = $r['adapter']   ?? null;
    $fixture = $r['fixture']   ?? null;
    $ratioRaw = $r['ratio_raw'] ?? null;
    $ratioGz  = $r['ratio_gz']  ?? null;
    $parsesOk = $r['parses_ok'] ?? null;
    if (!is_string($adapter) || !is_string($fixture)) {
        continue;
    }
    if (!is_float($ratioRaw) && !is_int($ratioRaw)) {
        continue;
    }
    if (!is_float($ratioGz) && !is_int($ratioGz)) {
        continue;
    }
    if (!is_bool($parsesOk)) {
        continue;
    }
    $compressionRows[] = [
        'adapter'   => $adapter,
        'fixture'   => $fixture,
        'ratio_raw' => (float) $ratioRaw,
        'ratio_gz'  => (float) $ratioGz,
        'parses_ok' => $parsesOk,
    ];
}

$md = ReportRenderer::render([
    'header'      => $header,
    'speed'       => $speed,
    'compression' => $compressionRows,
]);

file_put_contents($outPath, $md);
echo "wrote $outPath\n";

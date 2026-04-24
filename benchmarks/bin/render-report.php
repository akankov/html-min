#!/usr/bin/env php
<?php

declare(strict_types=1);

ini_set('display_errors', 'stderr');

require __DIR__ . '/../vendor/autoload.php';

use Akankov\HtmlMinBench\AdapterRegistry;
use Akankov\HtmlMinBench\Report\ReportRenderer;

if ($argc < 4) {
    fwrite(STDERR, "Usage: render-report.php <phpbench.xml> <compression.json> <out.md>\n");
    exit(1);
}
[$_, $benchPath, $compPath, $outPath] = $argv;

$comp = json_decode((string) file_get_contents($compPath), true, flags: JSON_THROW_ON_ERROR);

// PHPBench --dump-file emits XML. Parse it into the shape ReportRenderer expects.
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
    fwrite(STDERR, "render-report: extracted 0 speed rows from $benchPath — PHPBench dump schema mismatch?\n");
    exit(2);
}

$header = [
    'generated_at' => $comp['generated_at'] ?? gmdate('c'),
    'php_version'  => $comp['php_version']  ?? PHP_VERSION,
    'git_sha'      => getenv('BENCH_GIT_SHA') ?: 'unknown',
    'host'         => php_uname('s') . ' ' . php_uname('r'),
    'adapters'     => array_map(
        fn ($a) => ['name' => $a->name(), 'version' => $a->version(), 'unsafe' => $a->isUnsafeReference()],
        AdapterRegistry::all(),
    ),
];

$md = ReportRenderer::render([
    'header'      => $header,
    'speed'       => $speed,
    'compression' => array_map(
        fn ($r) => [
            'adapter'   => $r['adapter'],
            'fixture'   => $r['fixture'],
            'ratio_raw' => $r['ratio_raw'],
            'ratio_gz'  => $r['ratio_gz'],
            'parses_ok' => $r['parses_ok'],
        ],
        $comp['rows'] ?? [],
    ),
]);

file_put_contents($outPath, $md);
echo "wrote $outPath\n";

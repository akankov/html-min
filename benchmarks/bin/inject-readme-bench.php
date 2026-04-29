#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Extract the "## Summary" table from a benchmark report and substitute it
 * into a README between <!-- BENCH-START --> and <!-- BENCH-END --> markers.
 */
ini_set('display_errors', 'stderr');

$argv = $_SERVER['argv'] ?? null;

if (
    !is_array($argv)
    || !isset($argv[1], $argv[2])
    || !is_string($argv[1])
    || !is_string($argv[2])
) {
    fwrite(\STDERR, "Usage: inject-readme-bench.php <latest.md> <README.md>\n");
    exit(1);
}

$reportPath = $argv[1];
$readmePath = $argv[2];

$report = file_get_contents($reportPath);
if ($report === false) {
    fwrite(\STDERR, "inject-readme-bench: cannot read {$reportPath}\n");
    exit(2);
}

$readme = file_get_contents($readmePath);
if ($readme === false) {
    fwrite(\STDERR, "inject-readme-bench: cannot read {$readmePath}\n");
    exit(2);
}

if (preg_match('/^## Summary\s*\n\n(.+?)\n##\s/sm', $report, $m) !== 1) {
    fwrite(\STDERR, "inject-readme-bench: '## Summary' section not found in {$reportPath}\n");
    exit(3);
}
$summary = rtrim($m[1]);

$start = '<!-- BENCH-START -->';
$end   = '<!-- BENCH-END -->';
$pattern = '/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s';

if (preg_match($pattern, $readme) !== 1) {
    fwrite(\STDERR, "inject-readme-bench: BENCH-START / BENCH-END markers not found in {$readmePath}\n");
    exit(4);
}

$replacement = $start . "\n\n" . $summary . "\n\n" . $end;
$updated     = preg_replace($pattern, $replacement, $readme);

if ($updated === null || $updated === $readme) {
    echo "no change to {$readmePath}\n";
    exit(0);
}

file_put_contents($readmePath, $updated);
echo "updated {$readmePath} (Summary block synced from {$reportPath})\n";

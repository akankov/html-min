#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Fail the build when line coverage drops below a floor.
 *
 * PHPUnit can emit a Clover report but cannot fail on a coverage threshold;
 * this tiny checker closes that gap without pulling in a dependency. Reads the
 * project-level Clover <metrics> element and compares coveredstatements /
 * statements against the given minimum.
 *
 * Usage: php bin/coverage-check.php <clover.xml> <min-percent>
 */

$cloverPath = $argv[1] ?? '';
$min = isset($argv[2]) ? (float) $argv[2] : 0.0;

if ($cloverPath === '' || !is_file($cloverPath)) {
    fwrite(STDERR, "coverage-check: clover file not found: {$cloverPath}\n");
    exit(2);
}

$xml = simplexml_load_file($cloverPath);
if ($xml === false) {
    fwrite(STDERR, "coverage-check: could not parse clover file: {$cloverPath}\n");
    exit(2);
}

$metrics = $xml->project->metrics ?? null;
if ($metrics === null) {
    fwrite(STDERR, "coverage-check: no <project><metrics> element in clover report\n");
    exit(2);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];

if ($statements === 0) {
    fwrite(STDERR, "coverage-check: report contains zero statements\n");
    exit(2);
}

$percent = $covered / $statements * 100;
$rounded = round($percent, 2);

if ($percent + 1e-9 < $min) {
    fwrite(STDERR, sprintf(
        "coverage-check: line coverage %.2f%% (%d/%d) is below the %.2f%% floor\n",
        $rounded,
        $covered,
        $statements,
        $min,
    ));
    exit(1);
}

fwrite(STDOUT, sprintf(
    "coverage-check: line coverage %.2f%% (%d/%d) meets the %.2f%% floor\n",
    $rounded,
    $covered,
    $statements,
    $min,
));
exit(0);

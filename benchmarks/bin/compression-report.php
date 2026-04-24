#!/usr/bin/env php
<?php

declare(strict_types=1);

// Vendor libraries (notably voku/html-min on PHP 8.5) emit deprecation
// notices that would interleave with our JSON output on stdout. Route
// them to stderr so `make bench` can pipe stdout into the report renderer.
ini_set('display_errors', 'stderr');

require __DIR__ . '/../vendor/autoload.php';

use Akankov\HtmlMinBench\AdapterRegistry;
use Akankov\HtmlMinBench\Bench\CompressionReport;
use Akankov\HtmlMinBench\Corpus;

$rows = [];
foreach (AdapterRegistry::all() as $adapter) {
    foreach (Corpus::all() as $name => $html) {
        $rows[] = CompressionReport::measure($adapter, $name, $html);
    }
}

echo json_encode([
    'generated_at' => gmdate('c'),
    'php_version'  => PHP_VERSION,
    'rows'         => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

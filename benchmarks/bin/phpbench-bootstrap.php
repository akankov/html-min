<?php

declare(strict_types=1);

// Vendor libraries (notably voku/html-min on PHP 8.5) emit deprecation
// notices that PHPBench would otherwise interpret as benchmark "noise"
// because they land on the subprocess's stdout. Route them to stderr.
ini_set('display_errors', 'stderr');

require __DIR__ . '/../vendor/autoload.php';

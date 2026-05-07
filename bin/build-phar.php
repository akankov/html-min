<?php

declare(strict_types=1);

/**
 * Build dist/html-min.phar — a self-contained CLI binary bundling the
 * library, runtime PSR dependencies, and Composer's autoloader. The
 * resulting PHAR is invoked as `php html-min.phar input.html` (or
 * `./html-min.phar` once the file has executable permission).
 *
 * Run via `make phar`, or directly:
 *   php -d phar.readonly=Off bin/build-phar.php
 *
 * The build runs `composer install --no-dev` against a staging copy of
 * the project so the bundled autoloader only references runtime deps.
 * Without that, files-autoload entries from phpunit / phpstan / phan
 * transitive deps end up in vendor/composer/autoload_files.php and
 * the PHAR fatals at startup trying to require them.
 */

if (\ini_get('phar.readonly')) {
    fwrite(STDERR, "build-phar: php.ini has phar.readonly=On; rerun with `php -d phar.readonly=Off bin/build-phar.php`\n");
    exit(1);
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "build-phar: cannot resolve project root\n");
    exit(1);
}

$distDir    = $root . '/dist';
$stagingDir = $root . '/build/phar-staging';

ensureDir($distDir);

if (is_dir($stagingDir)) {
    rrmdir($stagingDir, $root . '/build');
}
ensureDir($stagingDir);

// Stage the project sources Composer needs to resolve the prod tree.
copyTree($root . '/src', $stagingDir . '/src');
copyTree($root . '/bin', $stagingDir . '/bin', static fn (string $rel): bool => $rel !== 'build-phar.php');
copyFileIfExists($root . '/composer.json', $stagingDir . '/composer.json');
copyFileIfExists($root . '/LICENSE', $stagingDir . '/LICENSE');

runComposerInstall($stagingDir);

$pharFile     = $distDir . '/html-min.phar';
$resolvedDist = realpath($distDir);
if (
    is_file($pharFile)
    && $resolvedDist !== false
    && realpath(dirname($pharFile)) === $resolvedDist
    && basename($pharFile) === 'html-min.phar'
) {
    // nosemgrep: php.lang.security.unlink-use.unlink-use
    unlink($pharFile);
}

$phar = new Phar($pharFile, 0, 'html-min.phar');
$phar->startBuffering();

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($stagingDir, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }

    $relative = substr($file->getPathname(), strlen($stagingDir) + 1);
    $phar->addFile($file->getPathname(), $relative);
}

$phar->setStub(<<<'STUB'
#!/usr/bin/env php
<?php
Phar::mapPhar('html-min.phar');
require 'phar://html-min.phar/bin/html-min';
__HALT_COMPILER();
STUB);

$phar->stopBuffering();
$phar->compressFiles(Phar::GZ);

chmod($pharFile, 0755);

$sizeKb = (int) round(filesize($pharFile) / 1024);
echo "build-phar: wrote {$pharFile} ({$sizeKb} KB)\n";

function ensureDir(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    if (!mkdir($path, 0755, true) && !is_dir($path)) {
        fwrite(STDERR, "build-phar: cannot create {$path}\n");
        exit(1);
    }
}

function copyFileIfExists(string $from, string $to): void
{
    if (is_file($from)) {
        copy($from, $to);
    }
}

/**
 * Copy a directory tree, optionally filtered by a callable that takes
 * the destination-relative path and returns true to include.
 */
function copyTree(string $from, string $to, ?callable $filter = null): void
{
    if (!is_dir($from)) {
        return;
    }
    ensureDir($to);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        $relative = substr($file->getPathname(), strlen($from) + 1);
        if ($filter !== null && !$filter($relative)) {
            continue;
        }
        $target = $to . '/' . $relative;
        if ($file->isDir()) {
            ensureDir($target);
        } else {
            copy($file->getPathname(), $target);
        }
    }
}

/**
 * Spawn `composer install --no-dev --optimize-autoloader` in $cwd via
 * proc_open with array-form arguments — bypasses the shell so there's
 * no injection surface even though the command line is fully internal.
 */
function runComposerInstall(string $cwd): void
{
    $pipes = [];
    // nosemgrep: php.lang.security.exec-use.exec-use
    $proc = proc_open(
        [
            'composer',
            'install',
            '--no-dev',
            '--optimize-autoloader',
            '--no-progress',
            '--no-interaction',
        ],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $cwd,
    );
    if (!\is_resource($proc)) {
        fwrite(STDERR, "build-phar: cannot spawn composer\n");
        exit(1);
    }

    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);

    if ($exitCode !== 0) {
        fwrite(STDERR, "build-phar: composer install failed (exit {$exitCode})\n");
        fwrite(STDERR, $stdout . $stderr);
        exit(1);
    }
}

/**
 * Recursively delete a directory, but only if it lives inside the
 * supplied bound directory — refuses to descend out of it. This is the
 * containment guarantee that lets us call unlink() on derived paths
 * inside the staging tree without opening a path-traversal hole.
 */
function rrmdir(string $dir, string $boundDir): void
{
    $resolvedBound = realpath($boundDir);
    $resolvedDir   = realpath($dir);
    if ($resolvedBound === false || $resolvedDir === false) {
        return;
    }
    if (!str_starts_with($resolvedDir . '/', $resolvedBound . '/')) {
        fwrite(STDERR, "build-phar: refusing to delete {$dir} (outside {$boundDir})\n");
        exit(1);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolvedDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            // nosemgrep: php.lang.security.unlink-use.unlink-use
            unlink($file->getPathname());
        }
    }
    rmdir($resolvedDir);
}

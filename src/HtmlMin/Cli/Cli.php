<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Cli;

use Akankov\HtmlMin\HtmlMin;

/**
 * Stream-driven CLI dispatcher for the `html-min` binary. The streams
 * are injected so the entry script in bin/ can wire them to STDIN /
 * STDOUT / STDERR while tests pass `php://memory` resources.
 */
final readonly class Cli
{
    private const string USAGE = <<<'TXT'
        Usage: html-min [INPUT] [OPTIONS]

          INPUT  Path to an HTML file. If omitted, reads from stdin.

        Options:
          -o, --output=PATH        Write minified HTML to PATH instead of stdout.
              --minify-inline-css  Minify the contents of inline <style> blocks.
              --minify-inline-js   Minify the contents of inline <script> blocks
                                   (JSON-LD and x-template scripts pass through).
          -h, --help               Show this message and exit.

        Exit codes:
          0  Success.
          1  I/O failure (cannot read INPUT or write to --output).
          2  Invalid argument.
        TXT;

    /**
     * @phpstan-param resource $stdin
     * @phpstan-param resource $stdout
     * @phpstan-param resource $stderr
     */
    public function __construct(
        private HtmlMin $minifier,
        private mixed $stdin,
        private mixed $stdout,
        private mixed $stderr,
    ) {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $args = $argv;
        array_shift($args); // drop the script name

        $inputPath  = null;
        $outputPath = null;

        foreach ($args as $arg) {
            if ($arg === '-h' || $arg === '--help') {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }

            if (str_starts_with($arg, '--output=')) {
                $outputPath = substr($arg, 9);

                continue;
            }

            if ($arg === '-o') {
                fwrite($this->stderr, "html-min: -o requires a path; use --output=PATH\n");

                return 2;
            }

            if ($arg === '--minify-inline-css') {
                $this->minifier->doMinifyInlineCss(true);

                continue;
            }

            if ($arg === '--minify-inline-js') {
                $this->minifier->doMinifyInlineJs(true);

                continue;
            }

            if (str_starts_with($arg, '-') && $arg !== '-') {
                fwrite($this->stderr, "html-min: unknown flag {$arg}\n");

                return 2;
            }

            if ($inputPath !== null) {
                fwrite($this->stderr, "html-min: only one input path is supported\n");

                return 2;
            }

            $inputPath = $arg;
        }

        $html = $this->readInput($inputPath);
        if ($html === null) {
            return 1;
        }

        $minified = $this->minifier->minify($html);

        return $this->writeOutput($outputPath, $minified);
    }

    private function readInput(?string $path): ?string
    {
        if ($path === null || $path === '-') {
            return (string) stream_get_contents($this->stdin);
        }

        if (!is_file($path) || !is_readable($path)) {
            fwrite($this->stderr, "html-min: cannot read input file: {$path}\n");

            return null;
        }

        $html = file_get_contents($path);
        if ($html === false) {
            fwrite($this->stderr, "html-min: cannot read input file: {$path}\n");

            return null;
        }

        return $html;
    }

    private function writeOutput(?string $path, string $minified): int
    {
        if ($path === null) {
            fwrite($this->stdout, $minified);

            return 0;
        }

        if (file_put_contents($path, $minified) === false) {
            fwrite($this->stderr, "html-min: cannot write output file: {$path}\n");

            return 1;
        }

        return 0;
    }
}

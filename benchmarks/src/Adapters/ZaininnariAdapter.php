<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Adapters;

use Composer\InstalledVersions;
use Override;
use Throwable;
use zz\Html\HTMLMinify;

final class ZaininnariAdapter implements MinifierAdapter
{
    #[Override]
    public function name(): string
    {
        return 'zaininnari/html-minifier';
    }

    #[Override]
    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('zaininnari/html-minifier') ?? 'unknown';
    }

    #[Override]
    public function minify(string $html): string
    {
        try {
            // Silence vendor's pre-PHP-7.3 continue-in-switch warnings and
            // dynamic-property deprecation. These fire on every call and would
            // otherwise make PHPBench measure PHP's error pipeline, not the minifier.
            $prev = error_reporting(error_reporting() & ~E_WARNING & ~E_DEPRECATED);
            try {
                $result = HTMLMinify::minify($html);
            } finally {
                error_reporting($prev);
            }
            return is_string($result) ? $result : '';
        } catch (Throwable) {
            // Failure is recorded downstream by CompressionReport::measure via parses_ok=false.
            return '';
        }
    }

    #[Override]
    public function isUnsafeReference(): bool
    {
        return false;
    }
}

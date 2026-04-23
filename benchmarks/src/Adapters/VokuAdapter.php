<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Adapters;

use Composer\InstalledVersions;
use Override;
use Throwable;
use voku\helper\HtmlMin;

final class VokuAdapter implements MinifierAdapter
{
    private readonly HtmlMin $impl;

    public function __construct()
    {
        $this->impl = new HtmlMin();
    }

    #[Override]
    public function name(): string
    {
        return 'voku/html-min';
    }

    #[Override]
    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('voku/html-min') ?? 'unknown';
    }

    #[Override]
    public function minify(string $html): string
    {
        try {
            return $this->impl->minify($html);
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

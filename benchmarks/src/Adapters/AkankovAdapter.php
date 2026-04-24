<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Adapters;

use Akankov\HtmlMin\HtmlMin;
use Composer\InstalledVersions;
use Override;
use Throwable;

final readonly class AkankovAdapter implements MinifierAdapter
{
    private HtmlMin $impl;

    public function __construct()
    {
        $this->impl = new HtmlMin();
    }

    #[Override]
    public function name(): string
    {
        return 'akankov/html-min';
    }

    #[Override]
    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('akankov/html-min') ?? 'unknown';
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

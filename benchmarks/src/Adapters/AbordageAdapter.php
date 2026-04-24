<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Adapters;

use Abordage\HtmlMin\HtmlMin;
use Composer\InstalledVersions;
use Override;
use Throwable;

final class AbordageAdapter implements MinifierAdapter
{
    private readonly HtmlMin $impl;

    public function __construct()
    {
        $this->impl = new HtmlMin();
    }

    #[Override]
    public function name(): string
    {
        return 'abordage/html-min';
    }

    #[Override]
    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('abordage/html-min') ?? 'unknown';
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
        return true;
    }
}

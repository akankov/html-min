<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Adapters;

use Akankov\HtmlMin\HtmlMin;
use Composer\InstalledVersions;
use Override;
use Throwable;

/**
 * Same engine as {@see AkankovAdapter} but with inline CSS and JS
 * minification enabled. Exists so the report shows the byte/compression
 * gain of the opt-in toggles alongside the default-config row — the
 * default adapter stays untuned for the apples-to-apples field comparison.
 */
final readonly class AkankovInlineAdapter implements MinifierAdapter
{
    private HtmlMin $impl;

    public function __construct()
    {
        $this->impl = (new HtmlMin())
            ->doMinifyInlineCss(true)
            ->doMinifyInlineJs(true);
    }

    #[Override]
    public function name(): string
    {
        return 'akankov/html-min (inline)';
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
            return '';
        }
    }

    #[Override]
    public function isUnsafeReference(): bool
    {
        return false;
    }
}

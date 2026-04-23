<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Adapters;

use Composer\InstalledVersions;
use Override;
use Throwable;
use WyriHaximus\HtmlCompress\Factory;
use WyriHaximus\HtmlCompress\HtmlCompressorInterface;

final class WyrihaximusAdapter implements MinifierAdapter
{
    private readonly HtmlCompressorInterface $impl;

    public function __construct()
    {
        $this->impl = Factory::construct();
    }

    #[Override]
    public function name(): string
    {
        return 'wyrihaximus/html-compress';
    }

    #[Override]
    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('wyrihaximus/html-compress') ?? 'unknown';
    }

    #[Override]
    public function minify(string $html): string
    {
        try {
            return $this->impl->compress($html);
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

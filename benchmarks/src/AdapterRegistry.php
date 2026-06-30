<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench;

use Akankov\HtmlMinBench\Adapters\AbordageAdapter;
use Akankov\HtmlMinBench\Adapters\AkankovAdapter;
use Akankov\HtmlMinBench\Adapters\AkankovInlineAdapter;
use Akankov\HtmlMinBench\Adapters\MinifierAdapter;
use Akankov\HtmlMinBench\Adapters\VokuAdapter;
use Akankov\HtmlMinBench\Adapters\ZaininnariAdapter;

final class AdapterRegistry
{
    /** @var list<MinifierAdapter>|null */
    private static ?array $cache = null;

    /**
     * @return list<MinifierAdapter>
     */
    public static function all(): array
    {
        return self::$cache ??= [
            new AkankovAdapter(),
            new AkankovInlineAdapter(),
            new VokuAdapter(),
            new ZaininnariAdapter(),
            new AbordageAdapter(),
        ];
    }
}

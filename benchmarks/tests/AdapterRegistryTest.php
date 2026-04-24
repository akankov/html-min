<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Tests;

use Akankov\HtmlMinBench\AdapterRegistry;
use Akankov\HtmlMinBench\Adapters\MinifierAdapter;
use PHPUnit\Framework\TestCase;

final class AdapterRegistryTest extends TestCase
{
    public function testAllReturnsExactlyFiveAdapters(): void
    {
        $adapters = AdapterRegistry::all();
        self::assertCount(5, $adapters);
    }

    public function testOrderIsStable(): void
    {
        $names = array_map(fn (MinifierAdapter $a) => $a->name(), AdapterRegistry::all());
        self::assertSame(
            [
                'akankov/html-min',
                'voku/html-min',
                'wyrihaximus/html-compress',
                'zaininnari/html-minifier',
                'abordage/html-min',
            ],
            $names,
        );
    }

    public function testAllEntriesImplementInterface(): void
    {
        foreach (AdapterRegistry::all() as $adapter) {
            self::assertInstanceOf(MinifierAdapter::class, $adapter);
        }
    }

    public function testNamesAreUnique(): void
    {
        $names = array_map(fn (MinifierAdapter $a) => $a->name(), AdapterRegistry::all());
        self::assertSame($names, array_unique($names));
    }
}

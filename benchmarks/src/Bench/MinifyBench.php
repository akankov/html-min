<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Bench;

use Akankov\HtmlMinBench\AdapterRegistry;
use Akankov\HtmlMinBench\Adapters\MinifierAdapter;
use Akankov\HtmlMinBench\Corpus;
use Generator;

/**
 * @BeforeMethods({"setUp"})
 * @Iterations(5)
 * @Revs(10)
 * @Warmup(1)
 * @Timeout(30.0)
 * @OutputTimeUnit("milliseconds")
 */
final class MinifyBench
{
    /** @var array<string, MinifierAdapter> */
    private array $adapters = [];

    /** @var array<string, string> */
    private array $corpus = [];

    public function setUp(): void
    {
        foreach (AdapterRegistry::all() as $a) {
            $this->adapters[$a->name()] = $a;
        }
        $this->corpus = Corpus::all();
    }

    /**
     * @ParamProviders({"provideCases"})
     */
    public function benchMinify(array $case): void
    {
        $this->adapters[$case['adapter']]->minify($this->corpus[$case['fixture']]);
    }

    public function provideCases(): Generator
    {
        foreach (AdapterRegistry::all() as $adapter) {
            foreach (array_keys(Corpus::all()) as $fixture) {
                yield "{$adapter->name()} / $fixture" => [
                    'adapter' => $adapter->name(),
                    'fixture' => $fixture,
                ];
            }
        }
    }
}

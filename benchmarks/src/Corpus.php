<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench;

use RuntimeException;

final class Corpus
{
    /**
     * @return array<string, string>  [fixture-name => html-content]
     */
    public static function small(): array
    {
        return self::load(__DIR__ . '/../fixtures/small');
    }

    /**
     * @return array<string, string>
     */
    public static function realWorld(): array
    {
        return self::load(__DIR__ . '/../fixtures/real-world');
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [...self::small(), ...self::realWorld()];
    }

    /**
     * @return array<string, string>
     */
    private static function load(string $dir): array
    {
        $out = [];
        foreach (glob($dir . '/*.html') ?: [] as $path) {
            $name = basename($path, '.html');
            $html = file_get_contents($path);
            if ($html === false) {
                throw new RuntimeException("cannot read fixture: $path");
            }
            $out[$name] = $html;
        }
        ksort($out);
        return $out;
    }
}

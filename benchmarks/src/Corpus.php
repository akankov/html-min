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
     * Synthetic stress fixtures generated on demand. They probe the cases the
     * curated corpus doesn't reach: many tiny repeated fragments, very deep
     * nesting, and attribute-heavy nodes.
     *
     * @return array<string, string>
     */
    public static function synthetic(): array
    {
        return [
            'repeated-fragments' => self::generateRepeatedFragments(),
            'deep-nesting'       => self::generateDeepNesting(),
            'attribute-heavy'    => self::generateAttributeHeavy(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [...self::small(), ...self::realWorld(), ...self::synthetic()];
    }

    private static function generateRepeatedFragments(int $count = 1000): string
    {
        $fragment = '<div class="card"><h3>Title %d</h3><p>Body text for card %d, '
                  . 'including some <em>emphasis</em> and a <a href="https://example.com/i/%d">link</a>.</p></div>';
        $body = '';
        for ($i = 0; $i < $count; ++$i) {
            $body .= \sprintf($fragment, $i, $i, $i);
        }

        return '<!DOCTYPE html><html lang="en"><head><title>repeated</title></head><body>' . $body . '</body></html>';
    }

    private static function generateDeepNesting(int $depth = 1000): string
    {
        return '<!DOCTYPE html><html><body>'
             . str_repeat('<div>', $depth)
             . 'leaf'
             . str_repeat('</div>', $depth)
             . '</body></html>';
    }

    private static function generateAttributeHeavy(int $nodes = 500, int $attrsPerNode = 20): string
    {
        $body = '';
        for ($i = 0; $i < $nodes; ++$i) {
            $attrs = '';
            for ($j = 0; $j < $attrsPerNode; ++$j) {
                $attrs .= \sprintf(' data-attr-%d="value-%d-%d"', $j, $i, $j);
            }
            $body .= '<span' . $attrs . '>n' . $i . '</span>';
        }

        return '<!DOCTYPE html><html><body>' . $body . '</body></html>';
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

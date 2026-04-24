<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Bench;

use Akankov\HtmlMinBench\Adapters\MinifierAdapter;
use DOMDocument;

final class CompressionReport
{
    /**
     * Measure one (adapter, fixture) pair and return a structured row.
     *
     * `parses_ok` is a LIVENESS check, not an HTML-validity check. It returns
     * true when the adapter produced non-empty output AND libxml's permissive
     * `DOMDocument::loadHTML` accepted it. libxml happily parses malformed
     * HTML (unclosed tags, tag soup), so this catches "adapter emitted empty
     * string" and "adapter emitted bytes libxml can't parse at all," but NOT
     * "adapter emitted subtly-broken HTML that would fail in a real browser."
     *
     * @return array{
     *     adapter:string, fixture:string, input_bytes:int, output_bytes:int,
     *     input_gzipped_bytes:int, output_gzipped_bytes:int,
     *     ratio_raw:float, ratio_gz:float, sha256_out:string, parses_ok:bool
     * }
     */
    public static function measure(MinifierAdapter $adapter, string $fixture, string $html): array
    {
        $out    = $adapter->minify($html);
        $inGz   = strlen(gzencode($html, 9) ?: '');
        $outGz  = strlen(gzencode($out, 9)  ?: '');

        return [
            'adapter'             => $adapter->name(),
            'fixture'             => $fixture,
            'input_bytes'         => strlen($html),
            'output_bytes'        => strlen($out),
            'input_gzipped_bytes' => $inGz,
            'output_gzipped_bytes'=> $outGz,
            'ratio_raw'           => strlen($html) === 0 ? 0.0 : strlen($out) / strlen($html),
            'ratio_gz'            => $inGz === 0         ? 0.0 : $outGz       / $inGz,
            'sha256_out'          => hash('sha256', $out),
            'parses_ok'           => $out !== '' && self::parses($out),
        ];
    }

    private static function parses(string $html): bool
    {
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = @$doc->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $ok && $doc->documentElement !== null;
    }
}

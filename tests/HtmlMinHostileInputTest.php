<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\HtmlMin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Robustness against hostile / adversarial input: content that collides with
 * the internal entity-preservation placeholders must not be corrupted on the
 * way out.
 */
final class HtmlMinHostileInputTest extends TestCase
{
    /**
     * The parser masks libxml-hostile characters with internal placeholder
     * tokens and reverses them after serialization. Caller content that
     * happens to contain a literal placeholder string must NOT be rewritten by
     * that reverse pass — it has to round-trip untouched.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function providePlaceholderLiteralsInInputSurviveUncorruptedCases(): iterable
    {
        yield 'entity sentinel in text'  => ['<p>____HTMLMIN_AMP____ stays literal</p>', '____HTMLMIN_AMP____'];
        yield 'url-bracket sentinel'     => ['<p>____HTMLMIN_BRACKET_LEFT____</p>', '____HTMLMIN_BRACKET_LEFT____'];
        yield 'percent sentinel'         => ['<p>____HTMLMIN_PERCENT____</p>', '____HTMLMIN_PERCENT____'];
        yield 'sentinel in attribute'    => ['<a title="____HTMLMIN_AMP____">x</a>', '____HTMLMIN_AMP____'];
        yield 'google-amp sentinel'      => ['<p>____HTMLMIN_GOOGLE_AMP____</p>', '____HTMLMIN_GOOGLE_AMP____'];
    }

    #[DataProvider('providePlaceholderLiteralsInInputSurviveUncorruptedCases')]
    public function testPlaceholderLiteralsInInputSurviveUncorrupted(string $input, string $sentinel): void
    {
        // The literal sentinel must appear verbatim in the output — i.e. the
        // restore pass did not mistake caller content for one of its tokens.
        $out = (new HtmlMin())->minify($input);

        self::assertStringContainsString($sentinel, $out, "Placeholder literal '{$sentinel}' was corrupted by minify().");
    }
}

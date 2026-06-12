<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\HtmlMin;
use Akankov\HtmlMin\Internal\HtmlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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

    /**
     * Property-style sweep over the real placeholder format: input that mimics
     * a full nonce-bearing token (`____HTMLMIN_{10 hex}_{TOKEN}____`) with a
     * randomly drawn fake nonce must round-trip verbatim. The live nonce is
     * regenerated per minify() run from 5 random bytes, so a fake one collides
     * with probability 2^-40 per iteration — the restore pass must treat all
     * of these as ordinary caller text.
     */
    public function testNonceBearingPlaceholderShapedInputSurvivesAcrossRuns(): void
    {
        $minifier = new HtmlMin();

        for ($i = 0; $i < 50; ++$i) {
            $fakeNonce = bin2hex(random_bytes(5));
            $token = ['AMP', 'PERCENT', 'BRACKET_LEFT', 'AT', 'PLUS'][$i % 5];
            $sentinel = '____HTMLMIN_' . $fakeNonce . '_' . $token . '____';

            $out = $minifier->minify("<p>x {$sentinel} y</p>");

            self::assertStringContainsString(
                $sentinel,
                $out,
                "Nonce-shaped literal '{$sentinel}' was corrupted on iteration {$i}.",
            );
        }
    }

    /**
     * The placeholder nonce is the unguessability guarantee behind the test
     * above: pin its shape (10 lowercase hex chars = 5 random bytes), its
     * stability within a run, and that {@see HtmlParser::reset()} — called at
     * the start of every minify() run — actually draws a fresh one each time.
     */
    public function testPlaceholderNonceIsFreshPerResetAndStableWithinARun(): void
    {
        $readNonce = static function (): string {
            $method = new ReflectionMethod(HtmlParser::class, 'placeholderNonce');
            $nonce = $method->invoke(null);
            \assert(\is_string($nonce));

            return $nonce;
        };

        $seen = [];
        for ($i = 0; $i < 32; ++$i) {
            HtmlParser::reset();
            $nonce = $readNonce();

            self::assertMatchesRegularExpression('/^[0-9a-f]{10}$/', $nonce);
            self::assertSame($nonce, $readNonce(), 'nonce must be stable within a run');
            $seen[$nonce] = true;
        }

        self::assertCount(32, $seen, 'every reset() must draw a fresh nonce');
    }
}

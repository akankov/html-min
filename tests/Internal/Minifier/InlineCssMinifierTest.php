<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Internal\Minifier;

use Akankov\HtmlMin\Internal\Minifier\InlineCssMinifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InlineCssMinifierTest extends TestCase
{
    public function testEmptyInputProducesEmptyOutput(): void
    {
        self::assertSame('', (new InlineCssMinifier())->minify(''));
    }

    public function testWhitespaceOnlyInputProducesEmptyOutput(): void
    {
        self::assertSame('', (new InlineCssMinifier())->minify("  \n\t  \r\n "));
    }

    public function testBlockCommentIsRemoved(): void
    {
        self::assertSame(
            'a{color:red}',
            (new InlineCssMinifier())->minify('a { color: red; /* danger */ }'),
        );
    }

    public function testTrailingSemicolonBeforeClosingBraceIsDropped(): void
    {
        self::assertSame(
            'a{color:red}',
            (new InlineCssMinifier())->minify('a { color: red; }'),
        );
    }

    public function testTrailingSemicolonRemovalDoesNotCorruptStrings(): void
    {
        // The `;}` trailing-semicolon optimisation must not reach inside a
        // verbatim-preserved string. `content:"x;}y"` contains a literal `;}`
        // that has to survive unchanged.
        self::assertSame(
            'a::before{content:"x;}y"}',
            (new InlineCssMinifier())->minify('a::before { content: "x;}y"; }'),
        );
    }

    public function testWhitespaceAroundSelectorsAndDeclarationsCollapses(): void
    {
        self::assertSame(
            'a,b{color:red;background:#fff}',
            (new InlineCssMinifier())->minify(
                "a,\n  b {\n    color: red;\n    background: #fff;\n  }",
            ),
        );
    }

    public function testCommentInsideStringIsPreserved(): void
    {
        // Quoted CSS content (e.g. `content:"/* keep */"`) must NOT have its
        // "comment" stripped — strings are atomic.
        self::assertSame(
            'a::before{content:"/* keep */"}',
            (new InlineCssMinifier())->minify(
                'a::before { content: "/* keep */"; }',
            ),
        );
    }

    public function testCommentInsideUrlIsPreserved(): void
    {
        // Bare `url(// foo)` is legal CSS — `//` is not a CSS line comment
        // and must survive the minifier.
        self::assertSame(
            'a{background:url(// foo)}',
            (new InlineCssMinifier())->minify('a { background: url(// foo); }'),
        );
    }

    public function testSingleQuotedStringPreservesInternalSpacing(): void
    {
        self::assertSame(
            "a::before{content:'  spaced  '}",
            (new InlineCssMinifier())->minify("a::before { content: '  spaced  '; }"),
        );
    }

    public function testMultipleRulesAreSeparatedWithoutWhitespace(): void
    {
        self::assertSame(
            'a{color:red}b{color:blue}',
            (new InlineCssMinifier())->minify(
                "a { color: red; }\n\nb { color: blue; }",
            ),
        );
    }

    public function testColonAndSemicolonPaddingIsCollapsed(): void
    {
        self::assertSame(
            'a{margin:0 1px}',
            (new InlineCssMinifier())->minify('a { margin : 0 1px ; }'),
        );
    }

    public function testNestedBlockCommentsAreFullyStripped(): void
    {
        // CSS doesn't actually nest comments per spec, but minifier must not
        // mistakenly treat `*/` inside a string as a comment terminator.
        self::assertSame(
            'a{color:red}',
            (new InlineCssMinifier())->minify('/* outer */a/* between */{color:red/* tail */}'),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRealWorldStylesheetsCases(): iterable
    {
        yield 'media query' => [
            "@media (min-width: 768px) {\n  .nav { display: flex; }\n}",
            '@media (min-width:768px){.nav{display:flex}}',
        ];

        yield 'keyframes' => [
            "@keyframes spin {\n  from { transform: rotate(0deg); }\n  to { transform: rotate(360deg); }\n}",
            '@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}',
        ];

        yield 'data uri url' => [
            'a { background: url("data:image/svg+xml;base64,PHN2Zw=="); }',
            'a{background:url("data:image/svg+xml;base64,PHN2Zw==")}',
        ];
    }

    #[DataProvider('provideRealWorldStylesheetsCases')]
    public function testRealWorldStylesheets(string $input, string $expected): void
    {
        self::assertSame($expected, (new InlineCssMinifier())->minify($input));
    }

    /**
     * Boundary behaviours of the scanner — comment handling, the `url(`
     * lookahead, string atomicity, and the deferred `;`. Each case pins an
     * edge that the happy-path tests leave unasserted.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function provideScannerEdgesCases(): iterable
    {
        // A comment between two values must collapse to a single separating
        // space, never vanish — otherwise the values fuse (`0 1px` → `01px`).
        yield 'comment between values keeps a space' => [
            'a { margin: 0 /* gap */ 1px; }',
            'a{margin:0 1px}',
        ];
        yield 'comment with no surrounding space still separates' => [
            'a{margin:0/* */1px}',
            'a{margin:0 1px}',
        ];

        // An unterminated comment runs to end-of-input and emits nothing more.
        yield 'unterminated comment runs to end of input' => [
            'a{color:red}/* oops',
            'a{color:red}',
        ];

        // url( detection is case-insensitive and the body is emitted verbatim
        // (inner whitespace preserved).
        yield 'uppercase URL( is detected and preserved verbatim' => [
            'a { background: URL( pic.png ) }',
            'a{background:URL( pic.png )}',
        ];
        // A property that merely starts with `u` must NOT trip the url( branch.
        yield 'u-prefixed property is not treated as url' => [
            'a { unicode-bidi: embed; }',
            'a{unicode-bidi:embed}',
        ];
        // A `)` inside a quoted url() argument must not end the url early.
        yield 'paren inside quoted url survives' => [
            'a{background:url("a)b")}',
            'a{background:url("a)b")}',
        ];

        // A redundant `;` collapses; the trailing one before `}` is dropped.
        yield 'double semicolon collapses' => [
            'a { color: red;; }',
            'a{color:red}',
        ];
    }

    #[DataProvider('provideScannerEdgesCases')]
    public function testScannerEdges(string $input, string $expected): void
    {
        self::assertSame($expected, (new InlineCssMinifier())->minify($input));
    }

    /**
     * Escape handling and graceful degradation on truncated input — the scanner
     * must not crash and must emit the (unclosed) string/url verbatim.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function provideMalformedAndEscapeCases(): iterable
    {
        yield 'escaped quote inside string' => ['a::before{content:"a\\"b"}', 'a::before{content:"a\\"b"}'];
        yield 'unterminated string runs to end' => ['a{content:"abc', 'a{content:"abc'];
        yield 'unterminated url runs to end' => ['a{background:url(abc', 'a{background:url(abc'];
    }

    #[DataProvider('provideMalformedAndEscapeCases')]
    public function testMalformedAndEscape(string $input, string $expected): void
    {
        self::assertSame($expected, (new InlineCssMinifier())->minify($input));
    }
}

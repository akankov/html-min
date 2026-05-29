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
}

<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Internal\Minifier;

use Akankov\HtmlMin\Internal\Minifier\InlineJsMinifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InlineJsMinifierTest extends TestCase
{
    public function testEmptyInputProducesEmptyOutput(): void
    {
        self::assertSame('', (new InlineJsMinifier())->minify(''));
    }

    public function testWhitespaceOnlyInputProducesEmptyOutput(): void
    {
        self::assertSame('', (new InlineJsMinifier())->minify("  \n\t\n\r "));
    }

    public function testLineCommentIsStrippedButNewlinePreserved(): void
    {
        self::assertSame(
            "let x = 1;\nlet y = 2;",
            (new InlineJsMinifier())->minify("let x = 1; // first\nlet y = 2;"),
        );
    }

    public function testBlockCommentIsReplacedWithSingleSpace(): void
    {
        // A block comment becomes a single space so identifiers don't fuse:
        // `a/*comment*/b` → `a b`, not `ab`.
        self::assertSame(
            'a b',
            (new InlineJsMinifier())->minify('a/*comment*/b'),
        );
    }

    public function testLineCommentInsideStringIsPreserved(): void
    {
        self::assertSame(
            'let x = "// not a comment";',
            (new InlineJsMinifier())->minify('let x = "// not a comment";'),
        );
    }

    public function testBlockCommentInsideStringIsPreserved(): void
    {
        self::assertSame(
            'let x = "/* keep */";',
            (new InlineJsMinifier())->minify('let x = "/* keep */";'),
        );
    }

    public function testSingleQuotedStringPreservesEscapes(): void
    {
        self::assertSame(
            "let x = 'it\\'s ok';",
            (new InlineJsMinifier())->minify("let x = 'it\\'s ok';"),
        );
    }

    public function testRegexLiteralIsPreservedVerbatim(): void
    {
        // The escaped `/` inside the regex must survive — naive line-comment
        // stripping would chew off the rest of the line.
        self::assertSame(
            'let r = /foo\\/bar/g;',
            (new InlineJsMinifier())->minify('let r = /foo\\/bar/g;'),
        );
    }

    public function testRegexThatLooksLikeBlockCommentIsPreserved(): void
    {
        self::assertSame(
            'let r = /\\/\\*hi\\*\\//g;',
            (new InlineJsMinifier())->minify('let r = /\\/\\*hi\\*\\//g;'),
        );
    }

    public function testDivisionIsNotMistakenForRegex(): void
    {
        // After an identifier or numeric literal, `/` is division, not the
        // start of a regex literal. The minifier must not gobble the rest of
        // the expression as a regex.
        self::assertSame(
            'let x = a / b / c;',
            (new InlineJsMinifier())->minify('let x = a / b / c;'),
        );
    }

    public function testPostfixIncrementBeforeDivisionIsNotRegex(): void
    {
        // After a postfix `++`/`--`, `/` is division, not the start of a regex
        // literal. Misreading it as a regex swallows the rest of the line
        // verbatim, so the run of whitespace around `/` would not collapse.
        self::assertSame(
            'let x = a++ / b;',
            (new InlineJsMinifier())->minify('let x = a++ /  b;'),
        );
    }

    public function testPostfixDecrementBeforeDivisionIsNotRegex(): void
    {
        self::assertSame(
            'let x = a-- / b;',
            (new InlineJsMinifier())->minify('let x = a-- /  b;'),
        );
    }

    public function testTemplateInterpolationWithBraceInStringDoesNotRunAway(): void
    {
        // A string inside `${…}` may contain an unbalanced brace (`"a{"`). The
        // template scanner must skip string contents when counting interpolation
        // braces, otherwise it misses the closing backtick and runs past the
        // template, swallowing the trailing code verbatim (no space collapse).
        self::assertSame(
            'let s = `${"a{"}`; let y = 1;',
            (new InlineJsMinifier())->minify('let s = `${"a{"}`;  let  y = 1;'),
        );
    }

    public function testTemplateLiteralIsPreservedVerbatim(): void
    {
        self::assertSame(
            'let s = `hello   ${name}   world`;',
            (new InlineJsMinifier())->minify('let s = `hello   ${name}   world`;'),
        );
    }

    public function testTemplateLiteralWithLineCommentLookalikeIsPreserved(): void
    {
        self::assertSame(
            'let s = `// not a comment`;',
            (new InlineJsMinifier())->minify('let s = `// not a comment`;'),
        );
    }

    public function testHorizontalWhitespaceRunsCollapse(): void
    {
        self::assertSame(
            'let x = 1;',
            (new InlineJsMinifier())->minify('let   x   =   1;'),
        );
    }

    public function testIndentationIsCollapsedButNewlinesArePreserved(): void
    {
        self::assertSame(
            "function f() {\nreturn 1;\n}",
            (new InlineJsMinifier())->minify(
                "function f() {\n  return 1;\n}",
            ),
        );
    }

    public function testMultipleBlankLinesCollapseToSingleNewline(): void
    {
        self::assertSame(
            "a;\nb;",
            (new InlineJsMinifier())->minify("a;\n\n\n\nb;"),
        );
    }

    public function testNewlineAfterReturnIsPreservedForAsi(): void
    {
        // ASI hazard: collapsing the newline would change `return\n42` from
        // `return;` to `return 42`. The bundled minifier preserves newlines
        // outside strings/comments/regex/templates.
        self::assertSame(
            "return\n42;",
            (new InlineJsMinifier())->minify("return\n42;"),
        );
    }

    public function testLineCommentAtEndOfFileWithoutNewline(): void
    {
        self::assertSame(
            'let x = 1;',
            (new InlineJsMinifier())->minify('let x = 1; // trailing'),
        );
    }

    public function testUnterminatedStringDoesNotCrash(): void
    {
        // Malformed input: degrade gracefully, emit as-is.
        $result = (new InlineJsMinifier())->minify('let x = "oops');
        self::assertStringContainsString('"oops', $result);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRealWorldSnippetsCases(): iterable
    {
        yield 'jquery-style guard' => [
            "/* prevent FOUC */\n(function(){\n  document.documentElement.classList.add('js');\n})();",
            "(function(){\ndocument.documentElement.classList.add('js');\n})();",
        ];

        yield 'arrow with template' => [
            'const greet = (name) => {' . "\n" . '  return `Hello, ${name}!`;' . "\n" . '};',
            'const greet = (name) => {' . "\n" . 'return `Hello, ${name}!`;' . "\n" . '};',
        ];

        yield 'comment between identifiers' => [
            'let /*a*/x/*b*/ = 1;',
            'let x = 1;',
        ];
    }

    #[DataProvider('provideRealWorldSnippetsCases')]
    public function testRealWorldSnippets(string $input, string $expected): void
    {
        self::assertSame($expected, (new InlineJsMinifier())->minify($input));
    }

    /**
     * Boundary behaviours of the regex-vs-division heuristic and the
     * string/regex/template scanners. Each case pins an edge the happy-path
     * tests leave unasserted; the doubled space around `/` is the tell — it
     * only collapses when `/` is correctly read as division (a mis-scanned
     * regex would emit the run verbatim).
     *
     * @return iterable<string, array{string, string}>
     */
    public static function provideScannerEdgesCases(): iterable
    {
        // `/` after `)`, `]`, or a digit is division, not a regex.
        yield 'division after closing paren' => [
            'let n = (a + b) /  c;',
            'let n = (a + b) / c;',
        ];
        yield 'division after index bracket' => [
            'let n = arr[0] /  c;',
            'let n = arr[0] / c;',
        ];
        yield 'division after numeric literal' => [
            'let n = 1 /  c;',
            'let n = 1 / c;',
        ];

        // `/` after a regex-context keyword IS a regex; its inner space survives.
        yield 'regex after return keyword' => [
            'function f(){return /a b/.test(x);}',
            'function f(){return /a b/.test(x);}',
        ];

        // A `/` inside a regex character class is literal — the regex ends only
        // at the `/` after the closing `]`.
        yield 'regex with slash inside character class' => [
            'let r = /[/ ]/g;',
            'let r = /[/ ]/g;',
        ];

        // An escaped backtick does not end the template; its content (including
        // the whitespace run) is emitted verbatim.
        yield 'escaped backtick inside template' => [
            'let s = `a\`b   c`;',
            'let s = `a\`b   c`;',
        ];

        // A nested template inside `${ … }` is scanned recursively, so its
        // content survives verbatim and its braces don't end the outer one early.
        yield 'nested template literal' => [
            'let s = `${`x   y`}`;',
            'let s = `${`x   y`}`;',
        ];
    }

    #[DataProvider('provideScannerEdgesCases')]
    public function testScannerEdges(string $input, string $expected): void
    {
        self::assertSame($expected, (new InlineJsMinifier())->minify($input));
    }

    /**
     * The token preceding `/` is classified by exact character-range checks
     * (`>= '0' && <= '9'` for numerics; `>= 'a' && <= 'z'` etc. for identifier
     * starts). These cases sit a single token *on each boundary* — `0`, `9`,
     * `a`, `z`, `A`, `Z`, `_`, `$` — so an off-by-one in a comparison flips the
     * `/` to a regex and the doubled space stops collapsing.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function provideRegexContextCharacterBoundariesCases(): iterable
    {
        yield 'after digit 0' => ['let n = 0 /  b;', 'let n = 0 / b;'];
        yield 'after digit 9' => ['let n = 9 /  b;', 'let n = 9 / b;'];
        yield 'after ident a' => ['let r = a /  2;', 'let r = a / 2;'];
        yield 'after ident z' => ['let r = z /  2;', 'let r = z / 2;'];
        yield 'after ident A' => ['let r = A /  2;', 'let r = A / 2;'];
        yield 'after ident Z' => ['let r = Z /  2;', 'let r = Z / 2;'];
        yield 'after ident _' => ['let r = _ /  2;', 'let r = _ / 2;'];
        yield 'after ident $' => ['let r = $ /  2;', 'let r = $ / 2;'];
    }

    #[DataProvider('provideRegexContextCharacterBoundariesCases')]
    public function testRegexContextCharacterBoundaries(string $input, string $expected): void
    {
        self::assertSame($expected, (new InlineJsMinifier())->minify($input));
    }

    /**
     * Scanner EOF / newline termination, escapes, and graceful degradation on
     * truncated input — every scan must terminate and emit the (unclosed) token
     * verbatim rather than crash or loop.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function provideScannerTerminationCases(): iterable
    {
        yield 'block comment containing a newline' => ["a/*\n*/b", "a\nb"];
        yield 'crlf line ending' => ["a\r\nb", "a\nb"];
        yield 'regex after assignment operator' => ['x=/re/g;', 'x=/re/g;'];
        yield 'string terminated by raw newline' => ["x='a\nb';", "x='a\nb';"];
        yield 'regex terminated by raw newline' => ["r=/a\nb/;", "r=/a\nb/;"];
        yield 'unterminated regex runs to end' => ['let r = /abc', 'let r = /abc'];
        yield 'nested brace inside interpolation' => ['let s = `${ {x:1} }`;', 'let s = `${ {x:1} }`;'];
        yield 'unterminated template runs to end' => ['let s = `abc', 'let s = `abc'];
    }

    #[DataProvider('provideScannerTerminationCases')]
    public function testScannerTermination(string $input, string $expected): void
    {
        self::assertSame($expected, (new InlineJsMinifier())->minify($input));
    }
}

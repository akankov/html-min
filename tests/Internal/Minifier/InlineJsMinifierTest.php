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
}

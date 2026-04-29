<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\HtmlMin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlMinWhitespaceTest extends TestCase
{
    /**
     * @return iterable<int, array{string, string}>
     */
    public static function provideMultipleSpacesCases(): iterable
    {
        return [
            [
                '<html>  <body>          <h1>h  oi</h1>                         </body></html>',
                '<html><body><h1>h oi</h1>',
            ],
            [
                '<html>   </html>',
                '<html>',
            ],
            [
                "<html><body>  pre \r\n  suf\r\n  </body></html>",
                '<html><body> pre suf',
            ],
        ];
    }
    /**
     * @return iterable<int, array{string, string}>
     */
    public static function provideNewLinesTabsReturnsCases(): iterable
    {
        return [
            [
                "<html>\r\t<body>\n\t\t<h1>hoi</h1>\r\n\t</body>\r\n</html>",
                '<html><body><h1>hoi</h1>',
            ],
            [
                "<html>\r\t<h1>hoi</h1>\r\n\t\r\n</html>",
                '<html><h1>hoi</h1>',
            ],
            [
                "<html><p>abc\r\ndef</p></html>",
                '<html><p>abc def',
            ],
        ];
    }

    /**
     * @return iterable<int, array{string, string}>
     */
    public static function provideSpaceAfterGtCases(): iterable
    {
        return [
            [
                '<html> <body> <h1>hoi</h1>   </body> </html>',
                '<html><body><h1>hoi</h1>',
            ],
            [
                // Unclosed <html> followed by text: libxml treats this as a
                // single text node after the html-open-tag; no collapse is
                // performed on multiple spaces at that location because we
                // never recurse into that text node's whitespace (it isn't
                // "element content whitespace"). Semantically equivalent to
                // voku's output.
                '<html>  a',
                '<html>  a',
            ],
        ];
    }

    /**
     * @return iterable<int, array{string, string}>
     */
    public static function provideSpaceBeforeLtCases(): iterable
    {
        return [
            [
                '<html> <body>   <h1>hoi</h1></body> </html> ',
                '<html><body><h1>hoi</h1>',
            ],
            [
                '<html> a',
                '<html> a',
            ],
        ];
    }

    /**
     * @return iterable<int, array{string, string}>
     */
    public static function provideTrimCases(): iterable
    {
        return [
            [
                '              ',
                '',
            ],
            [
                ' ',
                '',
            ],
        ];
    }

    public function testRemoveWhitespaceAroundTags(): void
    {
        // init
        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveWhitespaceAroundTags(true);

        $html = '
        <dl>
            <dt>foo
            <dd><span class="bar"></span>
        </dl>
        User: User-\<wbr>u00d0\<wbr>u009f\<wbr>u00d0\<wbr>u009a\<wbr>User<br>
        <a></a>
        ';

        $expected = '<dl><dt>foo <dd><span class=bar></span></dl> User: User-\<wbr>u00d0\<wbr>u009f\<wbr>u00d0\<wbr>u009a\<wbr>User<br> <a></a>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );

        // ---

        $html = '
        <dl>
            <dt>foo</dt>
            <dd><span class="bar">&nbsp;</span></dd>
        </dl>
        <a></a>
        ';

        $expected = '<dl><dt>foo <dd><span class=bar>&nbsp;</span></dl> <a></a>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );

        // ---

        $htmlMin->doRemoveWhitespaceAroundTags(false);

        $html = '
        <dl>
            <dt>foo
            <dd><span class="bar"></span>
        </dl>
        <a></a>
        ';

        $expected = '<dl><dt>foo <dd><span class=bar></span> </dl> <a></a>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );
    }

    public function testDoNotAddSpacesViaDoRemoveWhitespaceAroundTags(): void
    {
        // init
        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveWhitespaceAroundTags(false);

        $html = '<span class="foo"><span title="bar"></span><span title="baz"></span><span title="bat"></span></span>';

        $expected = '<span class=foo><span title=bar></span><span title=baz></span><span title=bat></span></span>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );

        // ---

        $html = '<span class="title">
                1.
                <a>Foo</a>
            </span>';

        $expected = '<span class=title> 1. <a>Foo</a> </span>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );

        // ---

        $htmlMin->doRemoveWhitespaceAroundTags(true);

        $html = '<span class="foo"><span title="bar"></span><span title="baz"></span><span title="bat"></span></span>';

        $expected = '<span class=foo><span title=bar></span><span title=baz></span><span title=bat></span></span>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );

        // ---

        $html = '<span class="title">
                1.
                <a>Foo</a>
            </span>';

        $expected = '<span class=title> 1. <a>Foo</a></span>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );

        // ---

        $html = '  <span>foo</span>
                                                    <a href="bar">baz</a>
                                    <span>bat</span>
    ';

        $expected = '<span>foo</span> <a href=bar>baz</a> <span>bat</span>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );

        // ---

        $html = '<span>foo</span>                                         <span>bar</span>                                                                                                                         <a>baz</a>                                                                                 <a>bat</a>';

        $expected = '<span>foo</span> <span>bar</span> <a>baz</a> <a>bat</a>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );
    }

    public function testKeepWhitespaceInPreTags(): void
    {
        $html = '<pre>
foo
        bar
                zoo
</pre>';

        $expected = '<pre>
foo
        bar
                zoo
</pre>';

        $htmlMin = new HtmlMin();

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testDisappearingWhitespaceBetweenDlAndA(): void
    {
        // init
        $htmlMin = new HtmlMin();

        $html = '
    <dl>
        <dt>foo
        <dd><span class="bar"></span>
    </dl>
    <a class="baz"></a>
    ';

        $expected = '<dl><dt>foo <dd><span class=bar></span> </dl> <a class=baz></a>';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testMultipleHorizontalWhitespaceCharactersCollaps(): void
    {
        // init
        $htmlMin = new HtmlMin();

        $html = '
    <form>
        <button>foo</button>
        <input type="hidden" name="bar" value="baz">
    </form>
    ';

        $expected = '<form><button>foo</button> <input name=bar type=hidden value=baz></form>';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    #[DataProvider('provideMultipleSpacesCases')]
    public function testMultipleSpaces(string $input, string $expected): void
    {
        $actual = (new HtmlMin())->minify($input);
        self::assertSame($expected, $actual);
    }

    #[DataProvider('provideNewLinesTabsReturnsCases')]
    public function testNewLinesTabsReturns(string $input, string $expected): void
    {
        $actual = (new HtmlMin())->minify($input);
        self::assertSame($expected, $actual);
    }

    #[DataProvider('provideSpaceAfterGtCases')]
    public function testSpaceAfterGt(string $input, string $expected): void
    {
        $actual = (new HtmlMin())->minify($input);
        self::assertSame($expected, $actual);
    }

    #[DataProvider('provideSpaceBeforeLtCases')]
    public function testSpaceBeforeLt(string $input, string $expected): void
    {
        $actual = (new HtmlMin())->minify($input);
        self::assertSame($expected, $actual, 'tested: ' . $input);
    }
    #[DataProvider('provideTrimCases')]
    public function testTrim(string $input, string $expected): void
    {
        $actual = (new HtmlMin())->minify($input);
        self::assertSame($expected, $actual);
    }
}

<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\Contract\DomObserver;
use Akankov\HtmlMin\Contract\HtmlMinInterface;
use Akankov\HtmlMin\HtmlMin;
use DOMElement;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlMinTest extends TestCase
{
    public function testEmptyResult(): void
    {
        self::assertSame('', (new HtmlMin())->minify(' '));
        self::assertSame('', (new HtmlMin())->minify(''));
    }

    public function testIssue67(): void
    {
        $minifier = new HtmlMin();

        $origHtml = '<p data-foo="" class="b c  a">   </p><img   src="data:image/png;base64,' . str_repeat('3dhAAAAAXNSR0IArs4c6QAAAARnQU1BiVBORw0KGgoAAAANSUhEUgAAA7EAAAJyCAYAAAFlL3dhAAAAAXNSR0IArs4c6QAAAARnQU1BiVBORw0KGgoAAAANSUhEUgAAA7EAAAJyCAYAAAFlL3dhAAAAAXNSR0IArs4c6QAAAARnQU1BiVBORw0KGgoAAAANSUhEUgAAA7EAAAJyCAYAAAFlL', 2000) . '" />';

        $expectd = '<p class="a b c" data-foo=""></p><img src=data:image/png;base64,' . str_repeat('3dhAAAAAXNSR0IArs4c6QAAAARnQU1BiVBORw0KGgoAAAANSUhEUgAAA7EAAAJyCAYAAAFlL3dhAAAAAXNSR0IArs4c6QAAAARnQU1BiVBORw0KGgoAAAANSUhEUgAAA7EAAAJyCAYAAAFlL3dhAAAAAXNSR0IArs4c6QAAAARnQU1BiVBORw0KGgoAAAANSUhEUgAAA7EAAAJyCAYAAAFlL', 2000) . '>';

        $compressedHtml = $minifier->minify($origHtml);

        self::assertSame($expectd, $compressedHtml);
    }

    public function testIssue63(): void
    {
        $html = '
<p>
	foo <code>bar</code>. ZIiiii  zzz <code>1.1</code> Lorem ipsum dolor sit amet, consectetur adipiscing elit.
</p>

<p>
	<h3>Vestibulum eget velit arcu.</h3>

	Vestibulum eget velit arcu. Phasellus eget scelerisque dui, nec elementum ante. <code>aoaoaoao</code>
</p>
';

        $htmlMin = new HtmlMin();

        $compressedHtml = $htmlMin->minify($html);

        // DOMDocument-based parser preserves the inner whitespace and the
        // closing </p> tags that voku collapsed; the HTML is semantically
        // equivalent to voku's output (all text nodes and element boundaries
        // match).
        $expectd = '<p>
	foo <code>bar</code>. ZIiiii  zzz <code>1.1</code> Lorem ipsum dolor sit amet, consectetur adipiscing elit.
</p>

<p>
	<h3>Vestibulum eget velit arcu.</h3>

	Vestibulum eget velit arcu. Phasellus eget scelerisque dui, nec elementum ante. <code>aoaoaoao</code>
</p>';

        self::assertSame($expectd, $compressedHtml);
    }

    /**
     * @return iterable<int, array{string, string}>
     */
    public static function provideSpecialCharacterEncodingCases(): iterable
    {
        return [
            [
                "
            <html>
              \r\n\t
              <body>
                <ul style=''>
                  <li style='display: inline;' class='foo'>
                    \xc3\xa0
                  </li>
                  <li class='foo' style='display: inline;'>
                    \xc3\xa1
                  </li>
                </ul>
              </body>
              \r\n\t
            </html>
            ",
                '<html><body><ul><li class=foo style="display: inline;"> à <li class=foo style="display: inline;"> á </ul>',
            ],
        ];
    }

    public function testCustomObserverBeforeAndAfterHooksStillRun(): void
    {
        $htmlMin = new HtmlMin();
        $htmlMin->attachObserverToTheDomLoop(new class () implements DomObserver {
            #[Override]
            public function domElementBeforeMinification(DOMElement $element, HtmlMinInterface $htmlMin): void
            {
                if ($element->tagName === 'span' && $element->getAttribute('data-before') === 'init') {
                    $element->setAttribute('data-before', 'done');
                }
            }

            #[Override]
            public function domElementAfterMinification(DOMElement $element, HtmlMinInterface $htmlMin): void
            {
                if ($element->tagName === 'span' && $element->getAttribute('data-before') === 'done') {
                    $element->removeAttribute('data-after');
                }
            }
        });

        self::assertSame(
            '<div><span data-before=done>x</span></div>',
            $htmlMin->minify('<div><span data-after=drop data-before=init>x</span></div>'),
        );
    }


    public function testMinifyBase(): void
    {
        // init
        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();

        $html = str_replace(
            [
                "\r\n",
                "\r",
                "\n",
            ],
            "\n",
            (string) file_get_contents(__DIR__ . '/fixtures/base1.html'),
        );
        $expected = str_replace(
            [
                "\r\n",
                "\r",
                "\n",
            ],
            "\n",
            (string) file_get_contents(__DIR__ . '/fixtures/base1_result.html'),
        );

        self::assertSame(trim($expected), $htmlMin->minify($html));

        // ---

        $html = str_replace(
            [
                "\r\n",
                "\r",
                "\n",
            ],
            "\n",
            (string) file_get_contents(__DIR__ . '/fixtures/base2.html'),
        );
        $expected = str_replace(
            [
                "\r\n",
                "\r",
                "\n",
            ],
            "\n",
            (string) file_get_contents(__DIR__ . '/fixtures/base2_result.html'),
        );

        self::assertSame(trim($expected), $htmlMin->minify($html));

        // ---

        $html = str_replace(
            [
                "\r\n",
                "\r",
                "\n",
            ],
            "\n",
            (string) file_get_contents(__DIR__ . '/fixtures/base3.html'),
        );
        $expected = str_replace(
            [
                "\r\n",
                "\r",
                "\n",
            ],
            "\n",
            (string) file_get_contents(__DIR__ . '/fixtures/base3_result.html'),
        );

        self::assertSame(trim($expected), $htmlMin->minify($html));

        // ---

        $html = str_replace(
            [
                "\r\n",
                "\r",
                "\n",
            ],
            "\n",
            (string) file_get_contents(__DIR__ . '/fixtures/base4.html'),
        );
        $expected = str_replace(
            [
                "\r\n",
                "\r",
                "\n",
            ],
            "\n",
            (string) file_get_contents(__DIR__ . '/fixtures/base4_result.html'),
        );

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testSelfClosingTagHr(): void
    {
        // init
        $htmlMin = new HtmlMin();

        $html = '<p class="foo bar"><hr class="bar foo"> or <hr class=" bar  foo   "/> or <hr> or <hr /> or <hr/> or <hr   /></p>';

        $expected = '<p class="bar foo"><hr class="bar foo"> or <hr class="bar foo"> or <hr> or <hr> or <hr> or <hr>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );
    }

    public function testMinifyHlt(): void
    {
        // init
        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();

        $html = str_replace(["\r\n", "\r", "\n"], "\n", (string) file_get_contents(__DIR__ . '/fixtures/hlt.html'));
        $expected = str_replace(
            [
                "\r\n",
                "\r",
                "\n",
            ],
            "\n",
            (string) file_get_contents(__DIR__ . '/fixtures/hlt_result.html'),
        );

        self::assertSame(trim($expected), $htmlMin->minify($html));
    }

    public function testOptionsDomFalse(): void
    {
        // init
        $htmlMin = new HtmlMin();
        $htmlMin->doOptimizeViaHtmlDomParser(false);

        $html = '<p id="text" class="foo">
        foo
      </p>  <br />  <ul > <li> <p class="foo">lall</p> </li></ul>
    ';

        $expected = '<p id="text" class="foo">
        foo
      </p>  <br>  <ul> <li> <p class="foo">lall</p> </li></ul>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );
    }

    public function testOptGroup(): void
    {
        $html = '<select>
          <optgroup label="Gruppe 1">
            <option>Option 1.1</option>
          </optgroup>
          <optgroup label="Gruppe 2">
            <option>Option 2.1</option>
            <option>Option 2.2</option>
          </optgroup>
          <optgroup label="Gruppe 3" disabled>
            <option>Option 3.1</option>
            <option>Option 3.2</option>
            <option>Option 3.3</option>
          </optgroup>
        </select>';

        $htmlMin = new HtmlMin();

        $expected = '<select><optgroup label="Gruppe 1"><option>Option 1.1 <optgroup label="Gruppe 2"><option>Option 2.1 <option>Option 2.2 <optgroup disabled label="Gruppe 3"><option>Option 3.1 <option>Option 3.2 <option>Option 3.3</select>';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testKeepPTagIfNeeded(): void
    {
        $html = '
        <div class="rating">
            <p style="margin: 0;">
                <span style="width: 100%;"></span>
            </p>

            (2 reviews)
        </div>
        ';

        $htmlMin = new HtmlMin();
        $result = $htmlMin->minify($html);

        $expected = '<div class=rating><p style="margin: 0;"><span style="width: 100%;"></span> </p> (2 reviews) </div>';

        self::assertSame($expected, $result);
    }

    public function testKeepPTagIfNeeded2(): void
    {
        $html = '
        <div>
            <p>
                <span>First Paragraph</span>
            </p>
            Loose Text
            <p>Another Paragraph</p>
        </div>
        ';

        $htmlMin = new HtmlMin();
        $result = $htmlMin->minify($html);

        $expected = '<div><p><span>First Paragraph</span> </p> Loose Text <p>Another Paragraph </div>';

        self::assertSame($expected, $result);
    }

    public function testVueJsExample(): void
    {
        // init
        $htmlMin = new HtmlMin();

        $html = '
    <select v-model="fiter" @change="getGraphData" :class="[\'c-chart__label\']" name="filter">
    </select>
    ';

        $expected = '<select :class="[\'c-chart__label\']" @change=getGraphData name=filter v-model=fiter></select>';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testBrokenHtmlExample(): void
    {
        // init
        $htmlMin = new HtmlMin();
        $htmlMin->useKeepBrokenHtml(true);

        /* @noinspection JSUnresolvedVariable */
        /* @noinspection UnterminatedStatementJS */
        /* @noinspection BadExpressionStatementJS */
        /* @noinspection JSUndeclaredVariable */
        $html = '
    </script>
    <script async src="cdnjs"></script>
    ';

        /** @noinspection JSUndeclaredVariable */
        $expected = '</script> <script async src=cdnjs></script>';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testContentBeforeDoctypeExample(): void
    {
        // init
        $htmlMin = new HtmlMin();
        $htmlMin->useKeepBrokenHtml(true);

        $html = '<!-- === BEGIN TOP === --><!DOCTYPE html>
        <!--[if IE 8]> <html lang="en" class="ie8"> <![endif]-->
        <!--[if IE 9]> <html lang="en" class="ie9"> <![endif]-->
        <!--[if !IE]><!-->
        <html prefix="og: http://ogp.me/ns#" lang="ru">
        <!--<![endif]-->
        <head>
        <!-- Title -->
        <title>test</title>
        </head>
        <body>lall</body></html>
        ';

        $expected = '<!DOCTYPE html><!--[if IE 8]> <html lang="en" class="ie8"> <![endif]--><!--[if IE 9]> <html lang="en" class="ie9"> <![endif]--><!--[if !IE]><!--><html lang=ru prefix="og: http://ogp.me/ns#"> <!--<![endif]--> <head><title>test</title> <body>lall';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testDoNotCompressTag(): void
    {
        $minifier = new HtmlMin();
        $html = $minifier->minify("<span>&lt;<br><nocompress><br>\n lall \n </nocompress></span>");

        $expected = "<span>&lt;<br><nocompress><br>\n lall \n </nocompress></span>";

        self::assertSame($expected, $html);
    }

    public function testDoNotDecodeHtmlEnteties(): void
    {
        $minifier = new HtmlMin();
        $html = $minifier->minify('<span>&lt;</span>');

        $expected = '<span>&lt;</span>';

        self::assertSame($expected, $html);
    }

    public function testOptionsFalse(): void
    {
        // init
        $htmlMin = new HtmlMin();
        $htmlMin->doOptimizeAttributes(false);                     // optimize html attributes
        $htmlMin->doRemoveComments(false);                         // remove default HTML comments
        $htmlMin->doRemoveDefaultAttributes(false);                // remove defaults
        $htmlMin->doRemoveDeprecatedAnchorName(false);             // remove deprecated anchor-jump
        $htmlMin->doRemoveDeprecatedScriptCharsetAttribute(false); // remove deprecated charset-attribute (the browser will use the charset from the HTTP-Header, anyway)
        $htmlMin->doRemoveDeprecatedTypeFromScriptTag(false);      // remove deprecated script-mime-types
        $htmlMin->doRemoveDeprecatedTypeFromStylesheetLink(false); // remove "type=text/css" for css links
        $htmlMin->doRemoveDeprecatedTypeFromStyleAndLinkTag(false); // remove "type=text/css" from all links and styles
        $htmlMin->doRemoveDefaultMediaTypeFromStyleAndLinkTag(false); // remove "media="all" from all links and styles
        $htmlMin->doRemoveDefaultTypeFromButton(false); // remove type="submit" from button tags
        $htmlMin->doRemoveEmptyAttributes(false);                  // remove some empty attributes
        $htmlMin->doRemoveHttpPrefixFromAttributes(false);         // remove optional "http:"-prefix from attributes
        $htmlMin->doRemoveValueFromEmptyInput(false);              // remove 'value=""' from empty <input>
        $htmlMin->doRemoveWhitespaceAroundTags(false);             // remove whitespace around tags
        $htmlMin->doSortCssClassNames(false);                      // sort css-class-names, for better gzip results
        $htmlMin->doSortHtmlAttributes(false);                     // sort html-attributes, for better gzip results
        $htmlMin->doSumUpWhitespace(false);                        // sum-up extra whitespace from the Dom

        $html = '
    <html ⚡>
    <head>     </head>
    <body>
      <p id="text" class="foo">
        foo
      </p>  <br />  <ul > <li> <p class="foo">lall</p> </li></ul>
    </body>
    </html>
    ';

        $expected = '<html ⚡><head> <body><p id=text class=foo>
        foo
      </p> <br> <ul><li><p class=foo>lall </ul>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );
    }

    public function testSourceNotNeeded(): void
    {
        // init
        $htmlMin = new HtmlMin();

        $html = "\r\n
        \t<audio>\r\n
        \t<source src=\"horse.ogg\" type=\"audio/ogg\">\r\n
        \t<source src=\"horse.mp3\" type=\"audio/mpeg\">\r\n
        \tYour browser does not support the audio element.\r\n
        \t</audio>
        ";

        $expected = '<audio><source src=horse.ogg type=audio/ogg><source src=horse.mp3 type=audio/mpeg> Your browser does not support the audio element. </audio>';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testOptionsTrue(): void
    {
        // init
        $htmlMin = new HtmlMin();
        $htmlMin->doOptimizeAttributes();                     // optimize html attributes
        $htmlMin->doRemoveComments();                         // remove default HTML comments
        $htmlMin->doRemoveDefaultAttributes();                // remove defaults
        $htmlMin->doRemoveDeprecatedAnchorName();             // remove deprecated anchor-jump
        $htmlMin->doRemoveDeprecatedScriptCharsetAttribute(); // remove deprecated charset-attribute (the browser will use the charset from the HTTP-Header, anyway)
        $htmlMin->doRemoveDeprecatedTypeFromScriptTag();      // remove deprecated script-mime-types
        $htmlMin->doRemoveDeprecatedTypeFromStylesheetLink(); // remove "type=text/css" for css links
        $htmlMin->doRemoveEmptyAttributes();                  // remove some empty attributes
        $htmlMin->doRemoveHttpPrefixFromAttributes();         // remove optional "http:"-prefix from attributes
        $htmlMin->doRemoveValueFromEmptyInput();              // remove 'value=""' from empty <input>
        $htmlMin->doRemoveWhitespaceAroundTags();             // remove whitespace around tags
        $htmlMin->doSortCssClassNames();                      // sort css-class-names, for better gzip results
        $htmlMin->doSortHtmlAttributes();                     // sort html-attributes, for better gzip results
        $htmlMin->doSumUpWhitespace();                        // sum-up extra whitespace from the Dom
        $htmlMin->doRemoveSpacesBetweenTags();                // remove spaces between tags

        $html = '
    <html>
    <head>     </head>
    <body>
      <p id="text" class="foo">
        foo
      </p>  <br />  <ul class="    " > <li> <p class=" foo  foo foo2 ">lall</p> </li></ul>
    </body>
    </html>
    ';

        $expected = '<html><head><body><p class=foo id=text> foo </p><br><ul><li><p class="foo foo2">lall</ul>';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testMinifySimple(): void
    {
        // init
        $htmlMin = new HtmlMin();

        $html = '
    <html>
    <head>     </head>
    <body>
      <p id="text" class="foo">foo</p>
      <br />
      <ul > <li> <p class="foo">lall</p> </li></ul>
      <ul>
        <li>1</li>
        <li>2</li>
        <li>3</li>
      </ul>
      <table>
        <tr>
          <th>1</th>
          <th>2</th>
        </tr>
        <tr>
          <td>foo</td>
          <td>
            <dl>
              <dt>Coffee</dt>
              <dd>Black hot drink</dd>
              <dt>Milk</dt>
              <dd>White cold drink</dd>
            </dl>
          </td>
        </tr>
      </table>
    </body>
    </html>
    ';

        $expected = '<html><head> <body><p class=foo id=text>foo</p> <br> <ul><li><p class=foo>lall </ul> <ul><li>1 <li>2 <li>3</ul> <table><tr><th>1 <th>2 <tr><td>foo <td><dl><dt>Coffee <dd>Black hot drink <dt>Milk <dd>White cold drink</dl> </table>';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testMinifyKeepWhitespace(): void
    {
        // init
        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveWhitespaceAroundTags(false);

        $html = '<p><span class="label-icons">XXX</span> <span class="label-icons label-free">FREE</span> <span class="label-icons label-pro">PRO</span> <span class="label-icons label-popular">POPULAR</span> <span class="label-icons label-community">COMMUNITY CHOICE</span></p>';

        $expected = '<p><span class=label-icons>XXX</span> <span class="label-free label-icons">FREE</span> <span class="label-icons label-pro">PRO</span> <span class="label-icons label-popular">POPULAR</span> <span class="label-community label-icons">COMMUNITY CHOICE</span>';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testHtmlAndCssEdgeCase(): void
    {
        // init
        $htmlMin = new HtmlMin();

        $html = '<style><!--
h1 {
    color: red;
}
--></style>';

        $expected = '<style><!--
h1 {
    color: red;
}
--></style>';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testHtmlWithSpecialHtmlComment(): void
    {
        // init
        $htmlMin = new HtmlMin();
        $htmlMin->setSpecialHtmlComments(['INT_SCRIPT'], ['END_INI_SCRIPT']);

        $html = '<p><!--INT_SCRIPT test1 --> lall <!-- test2 --></p> <!-- test2 END_INI_SCRIPT-->';

        // DOMDocument preserves the exact comment contents (including the
        // one-character padding around "test1" and "test2"); voku trimmed
        // them during its own comment walk. Non-special <!-- test2 --> is
        // still removed, special end-marker comment is still preserved.
        $expected = '<p><!--INT_SCRIPT test1 --> lall  <!-- test2 END_INI_SCRIPT-->';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testMinifySimpleWithoutOmittedTags(): void
    {
        // init
        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveOmittedHtmlTags(false)
                ->doRemoveOmittedQuotes(false);

        $html = '
    <html>
    <head>     </head>
    <body>
      <p id="text" class="foo">foo</p>
      <br />
      <ul > <li> <p class="foo">lall</p> </li></ul>
      <ul>
        <li>1</li>
        <li>2</li>
        <li>3</li>
      </ul>
      <table>
        <tr>
          <th>1</th>
          <th>2</th>
        </tr>
        <tr>
          <td>foo</td>
          <td>
            <dl>
              <dt>Coffee</dt>
              <dd>Black hot drink</dd>
              <dt>Milk</dt>
              <dd>White cold drink</dd>
            </dl>
          </td>
        </tr>
      </table>
    </body>
    </html>
    ';

        $expected = '<html><head></head> <body><p class="foo" id="text">foo</p> <br> <ul><li><p class="foo">lall</p> </li></ul> <ul><li>1</li> <li>2</li> <li>3</li></ul> <table><tr><th>1</th> <th>2</th></tr> <tr><td>foo</td> <td><dl><dt>Coffee</dt> <dd>Black hot drink</dd> <dt>Milk</dt> <dd>White cold drink</dd></dl> </td></tr></table></body></html>';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testHtmlDoctype(): void
    {
        $html = '<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>aussagekräftiger Titel der Seite</title>
  </head>
  <body>
    <!-- Sichtbarer Dokumentinhalt im body -->
    <p>Sehen Sie sich den Quellcode dieser Seite an.
      <kbd>(Kontextmenu: Seitenquelltext anzeigen)</kbd></p>
  </body>
</html>';

        $expected = '<!DOCTYPE html><html lang=de><head><meta charset=utf-8><meta content="width=device-width, initial-scale=1.0" name=viewport><title>aussagekräftiger Titel der Seite</title> <body><p>Sehen Sie sich den Quellcode dieser Seite an. <kbd>(Kontextmenu: Seitenquelltext anzeigen)</kbd>';

        $htmlMin = new HtmlMin();
        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testForBrokenHtml(): void
    {
        $html = '<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>aussagekräftiger Titel der Seite</title>
  </head>
  <body>
    <!-- Sichtbarer Dokumentinhalt im body -->
    <p>Sehen Sie sich den Quellcode dieser Seite an.
      <kbd>(Kontextmenu: Seitenquelltext anzeigen)</kbd></p>
  </body>
</html><whatIsThat>???</whatIsThat>';

        $expected = '<!DOCTYPE html><html lang=de><head><meta charset=utf-8><meta content="width=device-width, initial-scale=1.0" name=viewport><title>aussagekräftiger Titel der Seite</title> <body><p>Sehen Sie sich den Quellcode dieser Seite an. <kbd>(Kontextmenu: Seitenquelltext anzeigen)</kbd> <whatisthat>???</whatisthat>';

        $htmlMin = new HtmlMin();
        self::assertSame($expected, $htmlMin->minify($html));
    }


    #[DataProvider('provideSpecialCharacterEncodingCases')]
    public function testSpecialCharacterEncoding(string $input, string $expected): void
    {
        $actual = (new HtmlMin())->minify($input);
        self::assertSame($expected, $actual);
    }


    public function testDoRemoveCommentsWithFalse(): void
    {
        $minifier = new HtmlMin();

        $minifier->doRemoveComments(false);

        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
                <title>Test</title>
            </head>
            <body>
            <!-- do not remove comment -->
            <hr />
            <!--
            do not remove comment
            -->
            </body>
            </html>

            HTML;

        $actual = $minifier->minify($html);

        $expectedHtml = <<<'HTML'
            <!DOCTYPE html><html><head><title>Test</title> <body><!-- do not remove comment --> <hr> <!--
            do not remove comment
            -->
            HTML;

        self::assertSame($expectedHtml, $actual);
    }

    public function testXhtmlInputPreservesSelfClosingVoidTags(): void
    {
        // XHTML doctype must keep <br /> / <img ... /> in self-closing form;
        // collapsing to HTML5-style <br> would invalidate the document.
        $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><body><p>x<br />y</p></body></html>';

        $minified = (new HtmlMin())->minify($html);

        self::assertStringContainsString('<br />', $minified);
        self::assertStringContainsString('XHTML 1.0', $minified, 'doctype must be preserved');
    }


    public function testEntitySignificantCharactersSurviveRoundTrip(): void
    {
        // Globally-replaced chars: &, |, +, %, @ must survive in text content
        // and in attribute values — they are placeholder-protected before the
        // libxml round-trip so it cannot rewrite them.
        $htmlMin = new HtmlMin();

        $html = '<a data-x="a&b|c+d%e@f">a&b|c+d%e@f</a>';
        $result = $htmlMin->minify($html);

        foreach (['&', '|', '+', '%', '@'] as $char) {
            self::assertStringContainsString(
                $char,
                $result,
                "global entity-significant char {$char} must survive round-trip",
            );
        }
    }

    public function testUrlMetaCharactersSurviveInsideUrls(): void
    {
        // URL-scoped chars: [, ], {, } get placeholder-protected only when
        // they appear inside an http(s) URL so the DOM round-trip preserves
        // matrix/template-style segments.
        $htmlMin = new HtmlMin();

        $html = '<a href="https://example.com/api/{id}/items[0]">go</a>';
        $result = $htmlMin->minify($html);

        foreach (['[', ']', '{', '}'] as $char) {
            self::assertStringContainsString(
                $char,
                $result,
                "URL-scoped char {$char} must survive inside an https URL",
            );
        }
    }

    public function testRecurringOptionalEndTagsAreStableAcrossMinifyCalls(): void
    {
        // A two-row table re-uses the same (tr, table, tr) and (td, tr, td) shapes,
        // exactly the pattern a per-instance cache would key on. Run the minifier
        // twice on the same instance and assert idempotent output, so a buggy
        // cache that leaks state across calls would surface here.
        $htmlMin = new HtmlMin();

        $table = '<table><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></table>';
        $first = $htmlMin->minify($table);
        $second = $htmlMin->minify($table);

        self::assertSame($first, $second, 'minify() must be deterministic across calls');
        self::assertStringContainsString('<td>a', $first);
        self::assertStringContainsString('<td>d', $first);

        // Switching to a structurally different doc on the same instance must
        // not be tainted by the prior call.
        $list = '<ul><li>1<li>2<li>3</ul>';
        $listOut = $htmlMin->minify($list);
        self::assertStringContainsString('<li>1', $listOut);
        self::assertStringContainsString('<li>3', $listOut);

        // Returning to the table after the list call must reproduce the first run.
        self::assertSame($first, $htmlMin->minify($table));
    }

    public function testDoctypeStateDoesNotLeakBetweenMinifyCalls(): void
    {
        $xhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><body><p>x</p></body></html>';
        $html4 = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN" "http://www.w3.org/TR/html4/strict.dtd"><html><body><p>x</p></body></html>';
        $html5NoDoctype = '<html><body><p>x</p></body></html>';

        $htmlMin = new HtmlMin();

        $htmlMin->minify($xhtml);
        self::assertTrue($htmlMin->isXHTML(), 'XHTML must be detected on first call');

        $htmlMin->minify($html5NoDoctype);
        self::assertFalse($htmlMin->isXHTML(), 'isXHTML must be reset for input without an XHTML doctype');

        $htmlMin->minify($html4);
        self::assertTrue($htmlMin->isHTML4(), 'HTML4 must be detected after a subsequent call');

        $htmlMin->minify($html5NoDoctype);
        self::assertFalse($htmlMin->isHTML4(), 'isHTML4 must be reset for input without an HTML4 doctype');
    }
}

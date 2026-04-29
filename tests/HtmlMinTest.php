<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\Contract\DomObserver;
use Akankov\HtmlMin\Contract\HtmlMinInterface;
use Akankov\HtmlMin\HtmlMin;
use DOMElement;

use const LIBXML_VERSION;

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


    public function testSpecialScriptTag(): void
    {
        // init
        $html = '
                <!doctype html>
        <html lang="fr">
        <head>
            <title>Test</title>
        </head>
        <body>
            A Body

            <script id="elements-image-1" type="text/html">
                <div class="place badge-carte">Place du Village<br>250m - 2mn à pied</div>
                <div class="telecabine badge-carte">Télécabine du Chamois<br>250m - 2mn à pied</div>
                <div class="situation badge-carte"><img src="https://domain.tld/assets/frontOffice/kneiss/template-assets/assets/dist/img/08ecd8a.png" alt=""></div>
            </script>

            <script id="elements-image-2" type="text/html">
                <div class="place badge-carte">Place du Village<br>250m - 2mn à pied</div>
                <div class="telecabine badge-carte">Télécabine du Chamois<br>250m - 2mn à pied</div>
                <div class="situation badge-carte"><img src="https://domain.tld/assets/frontOffice/kneiss/template-assets/assets/dist/img/08ecd8a.png" alt=""></div>
            </script>

            <script class="foobar" type="text/html">
                <div class="place badge-carte">Place du Village<br>250m - 2mn à pied</div>
                <div class="telecabine badge-carte">Télécabine du Chamois<br>250m - 2mn à pied</div>
                <div class="situation badge-carte"><img src="https://domain.tld/assets/frontOffice/kneiss/template-assets/assets/dist/img/08ecd8a.png" alt=""></div>
            </script>
            <script class="foobar" type="text/html">
                <div class="place badge-carte">Place du Village<br>250m - 2mn à pied</div>
                <div class="telecabine badge-carte">Télécabine du Chamois<br>250m - 2mn à pied</div>
                <div class="situation badge-carte"><img src="https://domain.tld/assets/frontOffice/kneiss/template-assets/assets/dist/img/08ecd8a.png" alt=""></div>
            </script>
        </body>
        </html>
        ';

        $expected = '<!DOCTYPE html><html lang=fr><head><title>Test</title> <body> A Body <script id=elements-image-1 type=text/html><div class="badge-carte place">Place du Village<br>250m - 2mn à pied</div> <div class="badge-carte telecabine">Télécabine du Chamois<br>250m - 2mn à pied</div> <div class="badge-carte situation"><img alt="" src=https://domain.tld/assets/frontOffice/kneiss/template-assets/assets/dist/img/08ecd8a.png></div></script> <script id=elements-image-2 type=text/html><div class="badge-carte place">Place du Village<br>250m - 2mn à pied</div> <div class="badge-carte telecabine">Télécabine du Chamois<br>250m - 2mn à pied</div> <div class="badge-carte situation"><img alt="" src=https://domain.tld/assets/frontOffice/kneiss/template-assets/assets/dist/img/08ecd8a.png></div></script> <script class=foobar type=text/html><div class="badge-carte place">Place du Village<br>250m - 2mn à pied</div> <div class="badge-carte telecabine">Télécabine du Chamois<br>250m - 2mn à pied</div> <div class="badge-carte situation"><img alt="" src=https://domain.tld/assets/frontOffice/kneiss/template-assets/assets/dist/img/08ecd8a.png></div></script> <script class=foobar type=text/html><div class="badge-carte place">Place du Village<br>250m - 2mn à pied</div> <div class="badge-carte telecabine">Télécabine du Chamois<br>250m - 2mn à pied</div> <div class="badge-carte situation"><img alt="" src=https://domain.tld/assets/frontOffice/kneiss/template-assets/assets/dist/img/08ecd8a.png></div></script>';

        $htmlMin = new HtmlMin();

        $html = str_replace(["\r\n", "\r", "\n"], "\n", $html);
        $expected = str_replace(["\r\n", "\r", "\n"], "\n", $expected);

        self::assertSame(trim($expected), $htmlMin->minify($html));
    }

    public function testMinifyJsTagStuff(): void
    {
        $html = '<script type="text/javascript">alert("Hello");</script>';

        $expected = '<script>alert("Hello");</script>';

        $htmlMin = new HtmlMin();

        $html = str_replace(["\r\n", "\r", "\n"], "\n", $html);
        $expected = str_replace(["\r\n", "\r", "\n"], "\n", $expected);

        self::assertSame(trim($expected), $htmlMin->minify($html));
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

    public function testMinifyCodeTag(): void
    {
        // init
        $htmlMin = new HtmlMin();

        $html = str_replace(["\r\n", "\r", "\n"], "\n", (string) file_get_contents(__DIR__ . '/fixtures/code.html'));
        $expected = str_replace(
            [
                "\r\n",
                "\r",
                "\n",
            ],
            "\n",
            (string) file_get_contents(__DIR__ . '/fixtures/code_result.html'),
        );

        self::assertSame(trim($expected), $htmlMin->minify($html));
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

        self::assertSame(trim($expected), $htmlMin->minify($html, true));
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

    public function testCodeAndSpecialEncoding(): void
    {
        $html = '<pre class="line-numbers mb-0"><code class="language-php" id="code">&lt;?php if(!defined(\'NormanHuth\') &amp;&amp; NormanHuth!=\'Public\') die(\'Access denied\');' . "\r\n" . '</code></pre>';

        // Unlike voku's parser, libxml preserves CRLF byte-for-byte inside
        // <code> (protected content), so we expect the CR to survive.
        $expected = '<pre class="line-numbers mb-0"><code class="language-php" id="code">&lt;?php if(!defined(\'NormanHuth\') &amp;&amp; NormanHuth!=\'Public\') die(\'Access denied\');' . "\r\n" . '</code></pre>';

        $htmlMin = new HtmlMin();

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testMultiCode(): void
    {
        $html = '<code>foo</code> and <code>bar</code>';

        $expected = '<code>foo</code> and <code>bar</code>';

        $htmlMin = new HtmlMin();

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testStrongTagsSpecial(): void
    {
        $html = '
        <!DOCTYPE html>
<html lang="fr">
<head><title>Test</title></head>
<body>
<p>Visitez notre boutique <strong>eBay</strong> : <a href="https://foo.bar/lall" target="_blank">https://foo.bar/lall</a></p>
<p><strong>ID Vintage</strong>, spécialiste de la vente de pièces et accessoires pour motos tout- terrain classiques :<a href="https://foo.bar/123" target="_blank">https://foo.bar/123</a></p>
<p>Magazine <strong>Café-Racer</strong> : <a href="https://foo.bar/321" target="_blank">https://foo.bar/321</a></p>
<p><strong>Julien Lecointe</strong> : <a href="https://foo.bar/123456" target="_blank">https://foo.bar/123456</a></p>
</body>
</html>';

        $expected = '<!DOCTYPE html><html lang=fr><head><title>Test</title> <body><p>Visitez notre boutique <strong>eBay</strong> : <a href=https://foo.bar/lall target=_blank>https://foo.bar/lall</a> <p><strong>ID Vintage</strong>, spécialiste de la vente de pièces et accessoires pour motos tout- terrain classiques :<a href=https://foo.bar/123 target=_blank>https://foo.bar/123</a> <p>Magazine <strong>Café-Racer</strong> : <a href=https://foo.bar/321 target=_blank>https://foo.bar/321</a> <p><strong>Julien Lecointe</strong> : <a href=https://foo.bar/123456 target=_blank>https://foo.bar/123456</a>';

        $htmlMin = new HtmlMin();

        self::assertSame($expected, $htmlMin->minify($html));
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

    public function testTagsInsideJs(): void
    {
        $htmlWithJs = '<p>Text 1</p><script>$(".second-column-mobile-inner").wrapAll("<div class=\'collapse\' id=\'second-column\'></div>");</script><p>Text 2</p>';

        $htmlMin = new HtmlMin();
        $htmlMin->useKeepBrokenHtml(true);

        $expected = '<p>Text 1</p><script>$(".second-column-mobile-inner").wrapAll("<div class=\'collapse\' id=\'second-column\'><\/div>");</script><p>Text 2';

        self::assertSame($expected, $htmlMin->minify($htmlWithJs));
    }

    public function testHtmlInsideJavaScriptTemplates(): void
    {
        $html = '
<script type=text/html>
    <p>Foo</p>

    <div class="alert alert-success">
        Bar
    </div>

    {{foo}}

    {{bar}}

    {{hello}}
</script>
';

        $htmlMin = new HtmlMin();
        $htmlMin->overwriteTemplateLogicSyntaxInSpecialScriptTags(['{%']);

        $expected = '<script type=text/html><p>Foo <div class="alert alert-success"> Bar </div> {{foo}} {{bar}} {{hello}} </script>';

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testOverwriteSpecialScriptTags(): void
    {
        $html = <<<HTML
            <!doctype html>
                <html lang="nl">
                    <head></head>
                    <body>
                    <script type="text/x-custom">
                    <ul class="prices-tier items">
                      <% _.each(tierPrices, function(item, key) { %>
                      <%  var priceStr = '<span class="price-container price-tier_price">'
                              + '<span data-price-amount="' + priceUtils.formatPrice(item.price, currencyFormat) + '"'
                              + ' data-price-type=""' + ' class="price-wrapper ">'
                              + '<span class="price">' + priceUtils.formatPrice(item.price, currencyFormat) + '</span>'
                              + '</span>'
                          + '</span>'; %>
                      <li class="item">
                          <%= 'some text %1 %2'.replace('%1', item.qty).replace('%2', priceStr) %>
                          <strong class="benefit">
                             save <span class="percent tier-<%= key %>">&nbsp;<%= item.percentage %></span>%
                          </strong>
                      </li>
                      <% }); %>
                    </ul>
                    </script>
                    <div data-role="tier-price-block">
                        <div> Some Content </div>
                    </div>
                    </body>
            </html>
            HTML;
        $htmlMin = new HtmlMin();
        $htmlMin->overwriteSpecialScriptTags(['text/x-custom']);
        $expected = <<<HTML
            <!DOCTYPE html><html lang=nl><head> <body><script type=text/x-custom>
                    <ul class="prices-tier items">
                      <% _.each(tierPrices, function(item, key) { %>
                      <%  var priceStr = '<span class="price-container price-tier_price">'
                              + '<span data-price-amount="' + priceUtils.formatPrice(item.price, currencyFormat) + '"'
                              + ' data-price-type=""' + ' class="price-wrapper ">'
                              + '<span class="price">' + priceUtils.formatPrice(item.price, currencyFormat) + '</span>'
                              + '</span>'
                          + '</span>'; %>
                      <li class="item">
                          <%= 'some text %1 %2'.replace('%1', item.qty).replace('%2', priceStr) %>
                          <strong class="benefit">
                             save <span class="percent tier-<%= key %>">&nbsp;<%= item.percentage %></span>%
                          </strong>
                      </li>
                      <% }); %>
                    </ul>
                    </script> <div data-role=tier-price-block><div> Some Content </div> </div>
            HTML;


        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testHtmlClosingTagInSpecialScript(): void
    {
        $htmlMin = new HtmlMin();
        $htmlMin->doOptimizeViaHtmlDomParser(true);
        $html = $htmlMin->minify('
        <script id="comment-loader" type="text/x-handlebars-template">
            <nocompress>
                <i class="fas fa-spinner fa-pulse"></i> Loading ...
             </nocompress>
        </script>');

        // <nocompress> content is preserved byte-identically; whitespace
        // inside the special-script but OUTSIDE <nocompress> follows the
        // standard script-content rules (leading whitespace stripped,
        // trailing whitespace collapsed to a single space).
        $expected = '<script id=comment-loader type=text/x-handlebars-template><nocompress>
                <i class="fas fa-spinner fa-pulse"></i> Loading ...
             </nocompress> </script>';

        self::assertSame($expected, $html);
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

    public function testJavaScriptTemplateTag(): void
    {
        // init
        $htmlMin = new HtmlMin();

        $html = "
            <!doctype html>
            <html lang=\"nl\">
                <head>
                </head>
              <body>

              <div class=\"price-box price-tier_price\" data-role=\"priceBox\" data-product-id=\"1563\" data-price-box=\"product-id-1563\">
              </div>

              <script type=\"text/x-custom-template\" id=\"tier-prices-template\">
                <ul class=\"prices-tier items\">
                    <% _.each(tierPrices, function(item, key) { %>
                    <%  var priceStr = '<span class=\"price-container price-tier_price\">'
                            + '<span data-price-amount=\"' + priceUtils.formatPrice(item.price, currencyFormat) + '\"'
                            + ' data-price-type=\"\"' + ' class=\"price-wrapper \">'
                            + '<span class=\"price\">' + priceUtils.formatPrice(item.price, currencyFormat) + '</span>'
                            + '</span>'
                        + '</span>'; %>
                    <li class=\"item\">
                        <%= 'some text %1 %2'.replace('%1', item.qty).replace('%2', priceStr) %>
                        <strong class=\"benefit\">
                           save <span class=\"percent tier-<%= key %>\">&nbsp;<%= item.percentage %></span>%
                        </strong>
                    </li>
                    <% }); %>
                </ul>
              </script>

              <div data-role=\"tier-price-block\"></div>

              </body>
            </html>
            ";

        $expected = '<!DOCTYPE html><html lang=nl><head> <body><div class="price-box price-tier_price" data-price-box=product-id-1563 data-product-id=1563 data-role=priceBox></div> <script id=tier-prices-template type=text/x-custom-template>
                <ul class="prices-tier items">
                    <% _.each(tierPrices, function(item, key) { %>
                    <%  var priceStr = \'<span class="price-container price-tier_price">\'
                            + \'<span data-price-amount="\' + priceUtils.formatPrice(item.price, currencyFormat) + \'"\'
                            + \' data-price-type=""\' + \' class="price-wrapper ">\'
                            + \'<span class="price">\' + priceUtils.formatPrice(item.price, currencyFormat) + \'</span>\'
                            + \'</span>\'
                        + \'</span>\'; %>
                    <li class="item">
                        <%= \'some text %1 %2\'.replace(\'%1\', item.qty).replace(\'%2\', priceStr) %>
                        <strong class="benefit">
                           save <span class="percent tier-<%= key %>">&nbsp;<%= item.percentage %></span>%
                        </strong>
                    </li>
                    <% }); %>
                </ul>
              </script> <div data-role=tier-price-block></div>';

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
        $actual = (new HtmlMin())->minify($input, true);
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


    public function testNocompressDoesNotProtectSiblings(): void
    {
        // Whitespace inside a sibling element should still collapse even when
        // a <nocompress> appears in the same parent — only the <nocompress>
        // subtree should opt out of minification.
        $html = '<div><span>foo  bar</span><nocompress>keep  me</nocompress></div>';
        $expected = '<div><span>foo bar</span><nocompress>keep  me</nocompress></div>';

        self::assertSame($expected, (new HtmlMin())->minify($html));
    }

    public function testNullParentNode(): void
    {
        $html = ' <nocompress>foo</nocompress> ';
        $expected = '<nocompress>foo</nocompress>';

        $htmlMin = new HtmlMin();
        $htmlMin->doOptimizeViaHtmlDomParser(true);
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<><code>foo</code><>';
        // libxml2 >= 2.9.14 preserves the stray "<>" markers as text; older
        // versions silently drop them. Either outcome is acceptable for this
        // malformed input — test just guards against the null-parentNode crash.
        $expected = LIBXML_VERSION >= 20914
            ? '<><code>foo</code><>'
            : '<code>foo</code>';

        $htmlMin = new HtmlMin();
        $htmlMin->doOptimizeViaHtmlDomParser(true);
        self::assertSame($expected, $htmlMin->minify($html));
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

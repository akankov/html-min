<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\HtmlMin;

use const LIBXML_VERSION;

use PHPUnit\Framework\TestCase;

final class HtmlMinSpecialTagsTest extends TestCase
{
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

}

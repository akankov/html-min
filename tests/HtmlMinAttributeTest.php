<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\HtmlMin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlMinAttributeTest extends TestCase
{
    /**
     * @return iterable<int, array{string}>
     */
    public static function provideBoolAttrCases(): iterable
    {
        return [
            [
                '<input type="checkbox" autofocus="autofocus" checked="true" />',
            ],
            [
                '<input type="checkbox" autofocus="autofocus" checked="checked">',
            ],
            [
                '<input type="checkbox" autofocus="" checked="">',
            ],
            [
                '<input type="checkbox" autofocus="" checked>',
            ],
        ];
    }


    public function testSortedAttributesCanUpdateInPlace(): void
    {
        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();

        self::assertSame(
            '<a class=foo href=//example.com id=bar></a>',
            $htmlMin->minify('<a class="foo" href="http://example.com" id="bar"></a>'),
        );
    }

    #[DataProvider('provideBoolAttrCases')]
    public function testBoolAttr(string $input): void
    {
        $minifier = new HtmlMin();

        $html = '<!doctype html><html><body><form>' . $input . '</form></body></html>';
        $expected = '<!DOCTYPE html><html><body><form><input autofocus checked type=checkbox></form>';

        $actual = $minifier->minify($html);
        self::assertSame($expected, $actual);

        // ---

        $html = '<html><body><form>' . $input . '</form></body></html>';
        $expected = '<html><body><form><input autofocus checked type=checkbox></form>';

        $actual = $minifier->minify($html);
        self::assertSame($expected, $actual);

        // ---

        $html = '<form>' . $input . '</form>';
        $expected = '<form><input autofocus checked type=checkbox></form>';

        $actual = $minifier->minify($html);
        self::assertSame($expected, $actual);
    }
    public function testHtmlInAttribute(): void
    {
        // init
        $htmlMin = new HtmlMin();

        $html = '<button type="button" id="rotate_crop" class="btn btn-primary" data-loading-text="<i class=\'fa fa-spinner fa-spin\'></i> Rotando..." style="">Rotar</button>';

        $expected = '<button class="btn btn-primary" data-loading-text="<i class=\'fa fa-spinner fa-spin\'></i> Rotando..." id=rotate_crop type=button>Rotar</button>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );
    }

    public function testDataJsonInHtml(): void
    {
        // init
        $htmlMin = new HtmlMin();

        $html = '
        <html>
          <body>
            <div data-json=\'{"key":"value"}\'></div>
          </body>
        </html>';

        $expected = '<html><body><div data-json=\'{"key":"value"}\'></div>';

        self::assertSame(
            str_replace(["\r\n", "\r", "\n"], "\n", $expected),
            str_replace(["\r\n", "\r", "\n"], "\n", $htmlMin->minify($html)),
        );
    }

    public function testImageScrset(): void
    {
        $html = '
        <html lang="fr">
<head><title>Test</title></head>
<body>
<article class="row" itemscope itemtype="http://schema.org/Product">
<a href="https://www.gmp-classic.com/echappement_311_echappement-cafe-racer-bobber-classique-etc_paire-de-silencieux-type-megaton-lg-440-mm-__gmp11114.html" itemprop="url" tabindex="-1" class="product-image overlay col-sm-3">
    <img width="212" height="170"
         itemprop="image"
         srcset="http://cdn.gmp-classic.com/cache/images/product/5ee4535311159aaf1c4ae44fbebd83c2-p1000223_3800.jpg 768w,
                     https://cdn.gmp-classic.com/cache/images/product/82e8bafbecab56f932720490e7fc2f85-p1000223_3800.jpg 992w,
                     https://cdn.gmp-classic.com/cache/images/product/93c869f20df68d3e531f7e9c3e603e5e-p1000223_3800.jpg 1200w"
         sizes="(max-width: 768x) 354px,
                            (max-width: 992px) 305px,
                            212px"
         src="https://cdn.gmp-classic.com/cache/images/product/93c869f20df68d3e531f7e9c3e603e5e-p1000223_3800.jpg"
         class="img-responsive"
         alt="PAIRE DE SILENCIEUX  TYPE MEGATON Lg 440 mm">
</a>
</article>
</body>
</html>';

        $expected = '<html lang=fr><head><title>Test</title> <body><article class=row itemscope itemtype=http://schema.org/Product><a class="col-sm-3 overlay product-image" href=//www.gmp-classic.com/echappement_311_echappement-cafe-racer-bobber-classique-etc_paire-de-silencieux-type-megaton-lg-440-mm-__gmp11114.html itemprop=url tabindex=-1><img alt="PAIRE DE SILENCIEUX  TYPE MEGATON Lg 440 mm" class=img-responsive height=170 itemprop=image sizes="(max-width: 768x) 354px, (max-width: 992px) 305px, 212px" src=//cdn.gmp-classic.com/cache/images/product/93c869f20df68d3e531f7e9c3e603e5e-p1000223_3800.jpg srcset="//cdn.gmp-classic.com/cache/images/product/5ee4535311159aaf1c4ae44fbebd83c2-p1000223_3800.jpg 768w, //cdn.gmp-classic.com/cache/images/product/82e8bafbecab56f932720490e7fc2f85-p1000223_3800.jpg 992w, //cdn.gmp-classic.com/cache/images/product/93c869f20df68d3e531f7e9c3e603e5e-p1000223_3800.jpg 1200w" width=212> </a> </article>';

        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();
        $htmlMin->doRemoveHttpsPrefixFromAttributes();

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testSelfClosingInput(): void
    {
        $html = '
        <div class="form-group col-xl-10">
            <label for="chars">Zeichen</label>
            <div class="input-group">
                <input type="text" id="chars" class="form-control" value="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789![]{}()%&*$#^<>~@|" aria-describedby="chars-refresh-icon">
                <div class="input-group-append cursor-pointer" id="chars-refresh">
                    <div class="input-group-text" id="chars-refresh-icon"><i class="fas fa-undo fa-fw"></i></div>
                </div>
            </div>
        </div>
        ';

        $expected = '<div class="col-xl-10 form-group"><label for=chars>Zeichen</label> <div class=input-group><input aria-describedby=chars-refresh-icon class=form-control id=chars type=text value="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789![]{}()%&*$#^<>~@|"> <div class="cursor-pointer input-group-append" id=chars-refresh><div class=input-group-text id=chars-refresh-icon><i class="fa-fw fa-undo fas"></i></div> </div></div></div>';

        $htmlMin = new HtmlMin();
        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testRemoveDeprecatedTypeFromScriptTag(): void
    {
        $html = '<script type="text/javascript">alert("Hello");</script>
                <script type="text/ecmascript" src="ecmascript.js"></script>';
        // DOMDocument preserves the newline+indent between the two top-level
        // <script> tags as a single collapsed space (voku dropped it). The
        // type-attribute cleanup (the actual purpose of this test) still
        // happens as expected.
        $expected = '<script>alert("Hello");</script> <script src=ecmascript.js></script>';

        $htmlMin = new HtmlMin();
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<script type="text/javascript">alert("Hello");</script>
                <script type="text/ecmascript" src="ecmascript.js"></script>';
        // Same DOMDocument whitespace note as above: newline+indent between the
        // two top-level <script>s collapses to a single space.
        $expected = '<script type=text/javascript>alert("Hello");</script> <script src=ecmascript.js type=text/ecmascript></script>';

        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveDeprecatedTypeFromScriptTag(false);
        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testRelativeLinks(): void
    {
        $html = '<a href="https://www.example.com">Just an example</a>';
        $expected = '<a href=/>Just an example</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doMakeSameDomainsLinksRelative(['www.example.com']);
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<a href="www.example.com/">Just an example</a>';
        $expected = '<a href=/>Just an example</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doMakeSameDomainsLinksRelative(['https://www.example.com/']);
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<a href="www.example.com/foo/bar">Just an example</a>';
        $expected = '<a href=/foo/bar>Just an example</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doMakeSameDomainsLinksRelative(['httpS://www.example.com/']);
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<a href="www.example.com/foo/bar">Just an example</a><a href="www.google.com/foo/bar">Just an example v2</a>';
        $expected = '<a href=/foo/bar>Just an example</a><a href=/foo/bar>Just an example v2</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doMakeSameDomainsLinksRelative(['httpS://www.example.com/', 'httpS://www.google.com/']);
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<a href="HTTPS://www.example.com/foo/bar">Just an example</a>';
        $expected = '<a href=/foo/bar>Just an example</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doMakeSameDomainsLinksRelative(['www.Example.com']);
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<a href="HTTPS://موقع.وزارة-الاتصالات.مصر/foo/bar">Just an example</a>';
        $expected = '<a href=/foo/bar>Just an example</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doMakeSameDomainsLinksRelative(['موقع.وزارة-الاتصالات.مصر']);
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<a href=HTTPS://موقع.وزارة-الاتصالات.مصر/foo/bar target=_blank>Just an example</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doMakeSameDomainsLinksRelative(['موقع.وزارة-الاتصالات.مصر']);
        self::assertSame($html, $htmlMin->minify($html));

        // --

        $html = '<a href=HTTPS://موقع.وزارة-الاتصالات.مصر/foo/bar rel=external>Just an example</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doMakeSameDomainsLinksRelative(['موقع.وزارة-الاتصالات.مصر']);
        self::assertSame($html, $htmlMin->minify($html));
    }

    public function testdoKeepHttpAndHttpsPrefixOnExternalAttributes(): void
    {
        $html = '<a href="http://www.example.com/">No remove</a><img src="http://www.example.com/" />';
        $expected = '<a href=http://www.example.com/>No remove</a><img src=//www.example.com/>';

        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();
        $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes();
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<html><head><link href="http://www.example.com/"></head><body><a href="http://www.example.com/">No remove</a><img src="http://www.example.com/" /></body></html>';
        $expected = '<html><head><link href=//www.example.com/><body><a href=http://www.example.com/>No remove</a><img src=//www.example.com/>';

        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();
        $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes();
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<a target="_blank" href="http://www.example.com/">No remove</a><img src="http://www.example.com/" />';
        $expected = '<a href=http://www.example.com/ target=_blank>No remove</a><img src=//www.example.com/>';

        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();
        $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes();
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<html><head><link href="http://www.example.com/"></head><body><a target="_blank" href="http://www.example.com/">No remove</a><img src="http://www.example.com/" /></body></html>';
        $expected = '<html><head><link href=//www.example.com/><body><a href=http://www.example.com/ target=_blank>No remove</a><img src=//www.example.com/>';

        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();
        $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes();
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<a href="http://www.example.com/">No remove</a><img src="http://www.example.com/" />';
        $expected = '<a href=//www.example.com/>No remove</a><img src=//www.example.com/>';

        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();
        $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes(false);
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<html><head><link href="http://www.example.com/"></head><body><a href="http://www.example.com/">No remove</a><img src="http://www.example.com/" /></body></html>';
        $expected = '<html><head><link href=//www.example.com/><body><a href=//www.example.com/>No remove</a><img src=//www.example.com/>';

        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();
        $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes(false);
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<a target="_blank" href="http://www.example.com/">No remove</a><img src="http://www.example.com/" />';
        $expected = '<a href=http://www.example.com/ target=_blank>No remove</a><img src=//www.example.com/>';

        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();
        $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes(false);
        self::assertSame($expected, $htmlMin->minify($html));

        // --

        $html = '<html><head><link href="http://www.example.com/"></head><body><a target="_blank" href="http://www.example.com/">No remove</a><img src="http://www.example.com/" /></body></html>';
        $expected = '<html><head><link href=//www.example.com/><body><a href=http://www.example.com/ target=_blank>No remove</a><img src=//www.example.com/>';

        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();
        $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes(false);
        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testDoOptimizeAttributesFalseSkipsObserver(): void
    {
        // doOptimizeAttributes(false) is a master kill-switch: even if a
        // sub-flag like doRemoveHttpPrefixFromAttributes is on, the
        // OptimizeAttributes observer must not mutate attribute values.
        $html = '<a href="http://example.com/">x</a>';
        $expected = '<a href=http://example.com/>x</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doOptimizeAttributes(false);
        $htmlMin->doRemoveHttpPrefixFromAttributes(true);

        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testRelativeLinksRejectLookalikeDomains(): void
    {
        // www.example.com.evil.com is a different host — must not be rewritten
        $html = '<a href="http://www.example.com.evil.com/path">x</a>';
        $expected = '<a href=http://www.example.com.evil.com/path>x</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doMakeSameDomainsLinksRelative(['www.example.com']);
        self::assertSame($expected, $htmlMin->minify($html));

        // hyphen-extension is also a different domain
        $html = '<a href="http://www.example.com-evil.com/path">x</a>';
        $expected = '<a href=http://www.example.com-evil.com/path>x</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doMakeSameDomainsLinksRelative(['www.example.com']);
        self::assertSame($expected, $htmlMin->minify($html));
    }

    public function testHttpPrefixRemovalOnlyAffectsLeadingScheme(): void
    {
        // mid-string http:// inside a query parameter must not be touched
        $html = '<a href="http://example.com/?to=http://other.com">x</a>';
        $expected = '<a href="//example.com/?to=http://other.com">x</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpPrefixFromAttributes();
        $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes(false);
        self::assertSame($expected, $htmlMin->minify($html));

        // same guard for https://
        $html = '<a href="https://example.com/?to=https://other.com">x</a>';
        $expected = '<a href="//example.com/?to=https://other.com">x</a>';

        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveHttpsPrefixFromAttributes();
        $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes(false);
        self::assertSame($expected, $htmlMin->minify($html));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideDefaultAttributesAreRemovedCases(): iterable
    {
        return [
            'form method=get'                            => ['<form method="get"><input name="x"></form>', '<form><input name=x></form>'],
            'form autocomplete=on'                       => ['<form autocomplete="on"><input name="x"></form>', '<form><input name=x></form>'],
            'form enctype default'                       => ['<form enctype="application/x-www-form-urlencoded"><input name="x"></form>', '<form><input name=x></form>'],
            'input type=text'                            => ['<form><input type="text" name="x"></form>', '<form><input name=x></form>'],
            'textarea wrap=soft'                         => ['<form><textarea wrap="soft" name="x">y</textarea></form>', '<form><textarea name=x>y</textarea></form>'],
            'area shape=rect'                            => ['<map name="m"><area shape="rect" coords="1,2,3,4"></map>', '<map name=m><area coords=1,2,3,4></map>'],
            'th scope=auto'                              => ['<table><tr><th scope="auto">h</th></tr></table>', '<table><tr><th>h</table>'],
            'ol type=decimal'                            => ['<ol type="decimal"><li>x</li></ol>', '<ol><li>x</ol>'],
            'ol start=1'                                 => ['<ol start="1"><li>x</li></ol>', '<ol><li>x</ol>'],
            'track kind=subtitles'                       => ['<video><track kind="subtitles" src="x.vtt"></video>', '<video><track src=x.vtt></video>'],
            'spellcheck=default'                         => ['<div spellcheck="default">x</div>', '<div>x</div>'],
            'draggable=auto'                             => ['<div draggable="auto">x</div>', '<div>x</div>'],
            'script language=javascript'                 => ['<script language="javascript">var a=1;</script>', '<script>var a=1;</script>'],
        ];
    }

    #[DataProvider('provideDefaultAttributesAreRemovedCases')]
    public function testDefaultAttributesAreRemoved(string $input, string $expected): void
    {
        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveDefaultAttributes(true);
        self::assertSame($expected, $htmlMin->minify($input));
    }
}

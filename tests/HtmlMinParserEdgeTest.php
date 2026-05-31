<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\HtmlMin;
use PHPUnit\Framework\TestCase;

/**
 * Parser-preprocessing edges exercised through the public minify() pipeline:
 * embedded-SVG protection, junk before the doctype, and content after </html>.
 */
final class HtmlMinParserEdgeTest extends TestCase
{
    public function testEmbeddedSvgInDataUrlSurvives(): void
    {
        // libxml mangles raw <svg> in a data: URL (php bug #74628); the parser
        // stashes and restores it. The SVG markup must round-trip intact.
        $html = '<div style="background:url(\'data:image/svg+xml,<svg width=\'1\'><rect/></svg>\')">x</div>';

        self::assertSame($html, (new HtmlMin())->minify($html));
    }

    public function testEmptyEmbeddedSvgIsLeftAlone(): void
    {
        $html = '<div style="background:url(\'data:image/svg+xml,<svg></svg>\')">x</div>';

        self::assertSame($html, (new HtmlMin())->minify($html));
    }

    public function testJunkBeforeDoctypeIsStripped(): void
    {
        $out = (new HtmlMin())->minify('JUNK<!DOCTYPE html><html><body>x</body></html>');

        self::assertSame('<!DOCTYPE html><html><body>x', $out);
    }

    public function testContentAfterClosingHtmlIsRetained(): void
    {
        // libxml itself pulls trailing content back into the body — no explicit
        // preprocessing needed (the former </html>-trim was redundant here).
        $out = (new HtmlMin())->minify('<!DOCTYPE html><html><body>x</body></html>TAIL');

        self::assertSame('<!DOCTYPE html><html><body>x<p>TAIL', $out);
    }

    public function testTrailingElementAfterClosingHtmlIsRetained(): void
    {
        $out = (new HtmlMin())->minify('<!DOCTYPE html><html><body>x</body></html><div>after</div>');

        self::assertSame('<!DOCTYPE html><html><body>x<div>after</div>', $out);
    }

    public function testMultipleClosingHtmlTagsAreFlattenedByLibxml(): void
    {
        // Pathological malformed input with two </html> markers. Without the
        // former </html>-trim preprocessing, libxml flattens the trailing text
        // runs into a single paragraph instead of two. Both are arbitrary
        // handling of garbage; this pins the current (libxml-native) behavior.
        $out = (new HtmlMin())->minify('<!DOCTYPE html><html><body>x</body></html>mid</html>end');

        self::assertSame('<!DOCTYPE html><html><body>x<p>midend', $out);
    }
}

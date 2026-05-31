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
        $out = (new HtmlMin())->minify('<!DOCTYPE html><html><body>x</body></html>TAIL');

        self::assertSame('<!DOCTYPE html><html><body>x<p>TAIL', $out);
    }
}

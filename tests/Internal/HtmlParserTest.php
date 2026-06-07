<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Internal;

use Akankov\HtmlMin\Internal\HtmlParser;
use DOMDocument;
use DOMElement;

use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;

use PHPUnit\Framework\TestCase;

final class HtmlParserTest extends TestCase
{
    private static function doc(string $html): DOMDocument
    {
        $doc = new DOMDocument();
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        return $doc;
    }

    public function testSerializeReturnsHtmlString(): void
    {
        $doc = self::doc('<div class="a"><span>x</span></div>');

        self::assertStringContainsString('<div class="a"><span>x</span></div>', HtmlParser::serialize($doc));
    }

    public function testInnerHtmlOfElement(): void
    {
        $doc = self::doc('<div><span>x</span><img src="y"></div>');
        $div = $doc->getElementsByTagName('div')->item(0);
        self::assertInstanceOf(DOMElement::class, $div);

        self::assertSame('<span>x</span><img src="y">', HtmlParser::innerHtml($div));
    }

    public function testInnerHtmlOfDetachedElementIsEmpty(): void
    {
        // A DOMElement built directly (not via a document) has no ownerDocument.
        self::assertSame('', HtmlParser::innerHtml(new DOMElement('div')));
    }

    public function testSetInnerHtmlReplacesChildren(): void
    {
        $el = self::doc('<div>old</div>')->getElementsByTagName('div')->item(0);
        self::assertInstanceOf(DOMElement::class, $el);

        HtmlParser::setInnerHtml($el, '<b>hi</b><i>yo</i>');

        self::assertSame('<b>hi</b><i>yo</i>', HtmlParser::innerHtml($el));
    }

    public function testSetInnerHtmlWithEmptyStringClearsChildren(): void
    {
        $el = self::doc('<div><b>x</b></div>')->getElementsByTagName('div')->item(0);
        self::assertInstanceOf(DOMElement::class, $el);

        HtmlParser::setInnerHtml($el, '');

        self::assertSame(0, $el->childNodes->length);
    }

    public function testSetInnerHtmlOnDetachedElementIsANoOp(): void
    {
        $el = new DOMElement('div');
        HtmlParser::setInnerHtml($el, '<b>x</b>'); // no ownerDocument → returns early

        self::assertSame(0, $el->childNodes->length);
    }

    public function testFindAllFastPathTagSelectors(): void
    {
        $doc = self::doc('<div><span>a</span><span>b</span></div>');

        self::assertCount(2, HtmlParser::findAll($doc, 'span'));
        self::assertCount(3, HtmlParser::findAll($doc, '*')); // div + 2 spans
    }

    public function testFindAllFallsBackToXpathForAttributeSelectors(): void
    {
        // A selector part that is not a simple tag drops out of the fast path
        // into the xpath branch.
        $doc = self::doc('<div id="x"><span id="y" class="c">a</span></div>');

        self::assertCount(2, HtmlParser::findAll($doc, 'div, *[@id]')); // div#x + span#y
        self::assertCount(1, HtmlParser::findAll($doc, '*[@class]'));   // span.c
    }

    public function testFindAllReturnsEmptyForUnsupportedSelector(): void
    {
        // A CSS class selector is not supported and yields a malformed xpath;
        // the guard turns that into an empty result (no warning leaks).
        $doc = self::doc('<div class="c">a</div>');

        self::assertSame([], HtmlParser::findAll($doc, '.c'));
    }

    public function testFindAllOnNonElementRootUsesXpath(): void
    {
        $doc = self::doc('<div><span>text</span></div>');
        $textNode = $doc->getElementsByTagName('span')->item(0)?->firstChild;
        self::assertNotNull($textNode);

        // A DOMText root skips the element fast-path and goes through xpath('*').
        self::assertSame([], HtmlParser::findAll($textNode, '*'));
    }

    public function testFindAllOnOwnerlessNodeReturnsEmpty(): void
    {
        self::assertSame([], HtmlParser::findAll(new DOMElement('div'), 'span'));
    }

    public function testParseEmptyInputReturnsEmptyDocument(): void
    {
        HtmlParser::reset();

        $doc = HtmlParser::parse('');

        self::assertSame(0, $doc->childNodes->length);
    }

    public function testParseStripsContentBeforeDoctype(): void
    {
        HtmlParser::reset();

        $doc = HtmlParser::parse('garbage<!DOCTYPE html><html><body>x</body></html>');

        self::assertStringNotContainsString('garbage', HtmlParser::serialize($doc));
    }

    public function testSetInnerHtmlWithUnparseableContentIsANoOp(): void
    {
        // A NUL byte makes libxml fail to build the wrapper element; the guard
        // returns without touching the target.
        $el = self::doc('<div>old</div>')->getElementsByTagName('div')->item(0);
        self::assertInstanceOf(DOMElement::class, $el);

        HtmlParser::setInnerHtml($el, "\0");

        self::assertSame(0, $el->childNodes->length);
    }

    /**
     * reset() runs once at the start of every minify() run; it must hand each
     * run a fresh placeholder nonce so the unguessable tokens are never reused
     * across calls (defence-in-depth for long-lived / worker runtimes). Reading
     * the private static is the only way to observe this — the nonce never
     * appears in output because the restore pass always removes it.
     */
    public function testResetRegeneratesPlaceholderNoncePerRun(): void
    {
        HtmlParser::reset();
        HtmlParser::replaceToPreserveHtmlEntities('a & b'); // forces nonce generation
        $first = self::readPlaceholderNonce();

        HtmlParser::reset();
        HtmlParser::replaceToPreserveHtmlEntities('a & b');
        $second = self::readPlaceholderNonce();

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotSame($first, $second);
    }

    /**
     * Because the nonce is random per run, input that literally contains a
     * placeholder-shaped token can never collide with the live placeholders, so
     * the restore pass must leave it untouched rather than rewriting it.
     */
    public function testAdversarialPlaceholderShapedInputSurvivesRoundTrip(): void
    {
        HtmlParser::reset();

        $adversarial = '____HTMLMIN_deadbeef00_AT____change';
        $masked = HtmlParser::replaceToPreserveHtmlEntities($adversarial);
        $restored = HtmlParser::putReplacedBackToPreserveHtmlEntities($masked);

        self::assertStringContainsString($adversarial, $restored);
    }

    private static function readPlaceholderNonce(): ?string
    {
        $property = new \ReflectionProperty(HtmlParser::class, 'placeholderNonce');
        $value = $property->getValue();

        return is_string($value) ? $value : null;
    }
}

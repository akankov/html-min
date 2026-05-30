<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Internal;

use Akankov\HtmlMin\HtmlMin;
use Akankov\HtmlMin\Internal\OptionalTagOmission;
use DOMDocument;
use DOMElement;
use DOMNode;

use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;

use PHPUnit\Framework\TestCase;

final class OptionalTagOmissionTest extends TestCase
{
    private static function loadDoc(string $html): DOMDocument
    {
        $doc = new DOMDocument();
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        return $doc;
    }

    public function testAlwaysOptionalTags(): void
    {
        $doc = self::loadDoc('<body><p>x</p></body>');
        $body = $doc->getElementsByTagName('body')->item(0);
        self::assertInstanceOf(DOMElement::class, $body);

        self::assertTrue((new OptionalTagOmission())->isOptional($body));
    }

    public function testLiFollowedByLiIsOptional(): void
    {
        $doc = self::loadDoc('<ul><li>a</li><li>b</li></ul>');
        $firstLi = $doc->getElementsByTagName('li')->item(0);
        self::assertInstanceOf(DOMElement::class, $firstLi);

        self::assertTrue((new OptionalTagOmission())->isOptional($firstLi));
    }

    public function testNonOmittableTagIsNotOptional(): void
    {
        $doc = self::loadDoc('<div><span>a</span><span>b</span></div>');
        $span = $doc->getElementsByTagName('span')->item(0);
        self::assertInstanceOf(DOMElement::class, $span);

        self::assertFalse((new OptionalTagOmission())->isOptional($span));
    }

    public function testDetachedConditionalElementHasNoParent(): void
    {
        // A conditional-end-tag element with no parent exercises the
        // parent-is-null branch that never fires through the full pipeline
        // (where li/td/p always sit inside a document).
        $doc = new DOMDocument();
        $li = $doc->createElement('li');

        self::assertNull($li->parentNode);
        self::assertTrue((new OptionalTagOmission())->isOptional($li));
    }

    public function testNextSiblingElementSkipsInsignificantWhitespace(): void
    {
        $doc = self::loadDoc("<ul><li>a</li>\n   <li>b</li></ul>");
        $firstLi = $doc->getElementsByTagName('li')->item(0);
        self::assertInstanceOf(DOMElement::class, $firstLi);

        $next = OptionalTagOmission::nextSiblingElement($firstLi);

        self::assertInstanceOf(DOMElement::class, $next);
        self::assertSame('li', $next->tagName);
    }

    public function testHtmlMinDelegatingShimStillWorksForSubclasses(): void
    {
        // HtmlMin keeps a protected getNextSiblingOfTypeDOMElement() that now
        // delegates to OptionalTagOmission. This proves the retained
        // backward-compat shim behaves for any external subclass that calls it.
        $harness = new class () extends HtmlMin {
            public function exposeNextSibling(DOMNode $node): ?DOMNode
            {
                return $this->getNextSiblingOfTypeDOMElement($node);
            }
        };

        $doc = self::loadDoc('<ul><li>a</li><li>b</li></ul>');
        $firstLi = $doc->getElementsByTagName('li')->item(0);
        self::assertInstanceOf(DOMElement::class, $firstLi);

        $next = $harness->exposeNextSibling($firstLi);

        self::assertInstanceOf(DOMElement::class, $next);
        self::assertSame('li', $next->tagName);
    }
}

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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OptionalTagOmissionTest extends TestCase
{
    private static function loadDoc(string $html): DOMDocument
    {
        $doc = new DOMDocument();
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        return $doc;
    }

    /**
     * Characterization of the per-tag conditional end-tag truth table. Locks the
     * current behaviour (quirks included) so the rule-dispatch refactor is
     * provably equivalent. `$index` selects the nth element of `$tag`.
     */
    #[DataProvider('conditionalEndTagCases')]
    public function testConditionalEndTagOmission(string $html, string $tag, int $index, bool $expected): void
    {
        $doc = self::loadDoc($html);
        $element = $doc->getElementsByTagName($tag)->item($index);
        self::assertInstanceOf(DOMElement::class, $element);

        self::assertSame($expected, (new OptionalTagOmission())->isOptional($element));
    }

    /**
     * @return iterable<string, array{string, string, int, bool}>
     */
    public static function conditionalEndTagCases(): iterable
    {
        // li — followed by li or end of parent.
        yield 'li before li'        => ['<ul><li>a</li><li>b</li></ul>', 'li', 0, true];
        yield 'li at end'           => ['<ul><li>a</li></ul>', 'li', 0, true];
        yield 'li before div'       => ['<ul><li>a</li><div>x</div></ul>', 'li', 0, false];

        // optgroup — followed by optgroup or end of parent.
        yield 'optgroup before optgroup' => ['<select><optgroup><option>1</option></optgroup><optgroup><option>2</option></optgroup></select>', 'optgroup', 0, true];
        yield 'optgroup at end'          => ['<select><optgroup><option>1</option></optgroup></select>', 'optgroup', 0, true];

        // rp — followed by rp or rt, or end of parent.
        yield 'rp before rt'   => ['<ruby>A<rp>(</rp><rt>a</rt><rp>)</rp></ruby>', 'rp', 0, true];
        yield 'rp before text' => ['<ruby><rp>(</rp>x</ruby>', 'rp', 0, false];

        // tr — followed by tr or end of parent.
        yield 'tr before tr' => ['<table><tr><td>a</td></tr><tr><td>b</td></tr></table>', 'tr', 0, true];
        yield 'tr at end'    => ['<table><tr><td>a</td></tr></table>', 'tr', 0, true];

        // source — only inside audio/video/picture/source, followed by source or end.
        yield 'source in picture before source' => ['<picture><source srcset="a"><source srcset="b"><img src="c"></picture>', 'source', 0, true];
        yield 'source in div (parent not allowed)' => ['<div><source srcset="a"></div>', 'source', 0, false];

        // td / th — followed by td or th, or end of parent.
        yield 'td before td'   => ['<table><tr><td>a</td><td>b</td></tr></table>', 'td', 0, true];
        yield 'th before td'   => ['<table><tr><th>a</th><td>b</td></tr></table>', 'th', 0, true];
        yield 'td at end'      => ['<table><tr><td>a</td></tr></table>', 'td', 0, true];

        // dd / dt — current code treats both identically (next dd/dt or end of parent).
        yield 'dt before dd'   => ['<dl><dt>a</dt><dd>b</dd></dl>', 'dt', 0, true];
        yield 'dd before dt'   => ['<dl><dd>a</dd><dt>b</dt></dl>', 'dd', 0, true];
        yield 'dt at end (deviation, still true)' => ['<dl><dt>a</dt></dl>', 'dt', 0, true];
        yield 'dd before div'  => ['<dl><dd>a</dd><div>x</div></dl>', 'dd', 0, false];

        // option — followed by option or optgroup, or end of parent.
        yield 'option before option' => ['<select><option>1</option><option>2</option></select>', 'option', 0, true];
        yield 'option at end'        => ['<select><option>1</option></select>', 'option', 0, true];

        // p — followed by a block element, or end of parent (excluding certain parents).
        yield 'p before div'         => ['<div><p>a</p><div>x</div></div>', 'p', 0, true];
        yield 'p before span'        => ['<div><p>a</p><span>x</span></div>', 'p', 0, false];
        yield 'p at end of div'      => ['<div><p>a</p></div>', 'p', 0, true];
        yield 'p at end of ins'      => ['<ins><p>a</p></ins>', 'p', 0, false];
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

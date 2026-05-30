<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Internal;

use Akankov\HtmlMin\Internal\WhitespaceNormalizer;
use DOMDocument;
use DOMElement;

use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;

use PHPUnit\Framework\TestCase;

final class WhitespaceNormalizerTest extends TestCase
{
    private static function loadDoc(string $html): DOMDocument
    {
        $doc = new DOMDocument();
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        return $doc;
    }

    public function testRemoveAroundTagsCollapsesAdjacentWhitespace(): void
    {
        $doc = self::loadDoc('<div>  a  </div>');
        $div = $doc->getElementsByTagName('div')->item(0);
        self::assertInstanceOf(DOMElement::class, $div);

        WhitespaceNormalizer::removeAroundTags($div);

        // Runs of 2+ whitespace collapse to a single space in the inner text node.
        self::assertSame(' a ', $div->firstChild?->nodeValue);
    }

    public function testRemoveAroundTagsSkipsNonTextCandidates(): void
    {
        // When a trim-tag's first/last child is an element (not a text node),
        // it is skipped — exercises the non-text-candidate branch.
        $doc = self::loadDoc('<div><b>x</b></div>');
        $div = $doc->getElementsByTagName('div')->item(0);
        self::assertInstanceOf(DOMElement::class, $div);

        WhitespaceNormalizer::removeAroundTags($div);

        self::assertSame('<b>x</b>', $doc->saveHTML($div->firstChild));
    }

    public function testRemoveAroundTagsLeavesNonTrimTagsAlone(): void
    {
        $doc = self::loadDoc('<span>  a  </span>');
        $span = $doc->getElementsByTagName('span')->item(0);
        self::assertInstanceOf(DOMElement::class, $span);

        WhitespaceNormalizer::removeAroundTags($span);

        self::assertSame('  a  ', $span->firstChild?->nodeValue);
    }

    public function testSumUpCollapsesTextButPreservesProtectedAncestors(): void
    {
        $doc = self::loadDoc('<div><p>  a   b  </p><pre>  x   y  </pre></div>');

        WhitespaceNormalizer::sumUp($doc);

        $p = $doc->getElementsByTagName('p')->item(0);
        $pre = $doc->getElementsByTagName('pre')->item(0);
        self::assertInstanceOf(DOMElement::class, $p);
        self::assertInstanceOf(DOMElement::class, $pre);

        self::assertSame(' a b ', $p->firstChild?->nodeValue);
        self::assertSame('  x   y  ', $pre->firstChild?->nodeValue);
    }
}

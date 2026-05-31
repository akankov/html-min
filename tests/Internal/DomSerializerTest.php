<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Internal;

use Akankov\HtmlMin\HtmlMin;
use Akankov\HtmlMin\Internal\DomSerializer;
use Akankov\HtmlMin\Internal\OptionalTagOmission;
use DOMDocument;
use DOMNode;

use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;

use PHPUnit\Framework\TestCase;

final class DomSerializerTest extends TestCase
{
    private static function bodyDoc(string $inner): DOMDocument
    {
        $doc = new DOMDocument();
        $doc->loadHTML('<body>' . $inner . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        return $doc;
    }

    public function testSingleSpaceBetweenInlineElementsIsPreservedInBody(): void
    {
        // A single significant space between two inline elements in the body is
        // kept (whitespace-around-tags removal is off by default).
        $doc = self::bodyDoc('<span>a</span> <span>b</span>');
        $serializer = new DomSerializer(new HtmlMin(), new OptionalTagOmission());

        self::assertSame('<body><span>a</span> <span>b</span>', $serializer->toString($doc));
    }

    public function testWhitespaceBetweenHeadAndBodyElementsDiffers(): void
    {
        // The serializer suppresses inter-element whitespace inside <head>
        // (never significant) but preserves a single space between inline
        // elements in <body>. This is the parent === 'head' branch in toString().
        $html = '<html><head><meta name=a> <meta name=b></head>'
            . '<body><span>x</span> <span>y</span></body></html>';

        $out = (new HtmlMin())->minify($html);

        // No space survives between the two <meta> elements in <head>...
        self::assertStringContainsString('<meta name=a><meta name=b>', $out);
        // ...but the single space between the two body spans is kept.
        self::assertStringContainsString('<span>x</span> <span>y</span>', $out);
    }

    public function testSerializesElementsAttributesAndText(): void
    {
        $doc = self::bodyDoc('<p class="lead">hi</p>');
        $serializer = new DomSerializer(new HtmlMin(), new OptionalTagOmission());

        // doRemoveOmittedHtmlTags is on by default, so <p>'s end tag is omitted.
        self::assertSame('<body><p class=lead>hi', $serializer->toString($doc));
    }

    public function testAttributesToStringDropsBooleanAttributeValue(): void
    {
        $doc = self::bodyDoc('<input type="checkbox" checked="checked">');
        $input = $doc->getElementsByTagName('input')->item(0);
        self::assertNotNull($input);

        $attrs = (new DomSerializer(new HtmlMin(), new OptionalTagOmission()))
            ->attributesToString($input);

        // boolean attribute keeps the name, drops `=value`; values get quote-omitted.
        self::assertStringContainsString('checked', $attrs);
        self::assertStringNotContainsString('checked=', $attrs);
        self::assertStringContainsString('type=checkbox', $attrs);
    }

    public function testHtmlMinSerializerShimStillWorksForSubclasses(): void
    {
        // HtmlMin keeps a protected domNodeToString() that now delegates to
        // DomSerializer. This proves the retained backward-compat shim behaves
        // for any external subclass that calls it.
        $harness = new class () extends HtmlMin {
            public function exposeSerialize(DOMNode $node): string
            {
                return $this->domNodeToString($node);
            }
        };

        $doc = self::bodyDoc('<span>x</span>');

        self::assertSame('<body><span>x</span>', $harness->exposeSerialize($doc));
    }
}

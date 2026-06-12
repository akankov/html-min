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
        // libxml's HTML4-era parser warns on post-HTML4 elements (<details>,
        // <search>, …); collect-and-clear keeps those warnings out of PHPUnit
        // while the elements still land in the tree.
        $previous = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc;
    }

    /**
     * Characterization of the per-tag conditional end-tag truth table. Locks the
     * current behaviour (quirks included) so the rule-dispatch refactor is
     * provably equivalent. `$index` selects the nth element of `$tag`.
     */
    #[DataProvider('provideConditionalEndTagOmissionCases')]
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
    public static function provideConditionalEndTagOmissionCases(): iterable
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

        // option — followed by option, optgroup, or hr, or end of parent.
        yield 'option before option' => ['<select><option>1</option><option>2</option></select>', 'option', 0, true];
        yield 'option at end'        => ['<select><option>1</option></select>', 'option', 0, true];

        // optgroup — also omittable before <hr> (the <select> separator addition).
        yield 'optgroup before hr' => ['<select><optgroup><option>1</option></optgroup><hr><optgroup><option>2</option></optgroup></select>', 'optgroup', 0, true];

        // rt — same rule as rp: followed by rt or rp, or end of parent.
        yield 'rt before rp'   => ['<ruby>A<rt>a</rt><rp>)</rp></ruby>', 'rt', 0, true];
        yield 'rt before rt'   => ['<ruby>A<rt>a</rt><rt>b</rt></ruby>', 'rt', 0, true];
        yield 'rt at end'      => ['<ruby>A<rt>a</rt></ruby>', 'rt', 0, true];
        yield 'rt before text' => ['<ruby><rt>a</rt>x</ruby>', 'rt', 0, false];

        // p — followed by a block element, or end of parent (excluding certain parents).
        yield 'p before div'         => ['<div><p>a</p><div>x</div></div>', 'p', 0, true];
        yield 'p before span'        => ['<div><p>a</p><span>x</span></div>', 'p', 0, false];
        yield 'p at end of div'      => ['<div><p>a</p></div>', 'p', 0, true];
        yield 'p at end of ins'      => ['<ins><p>a</p></ins>', 'p', 0, false];

        // p — the post-HTML4 entries of the spec's followed-by list.
        yield 'p before details'    => ['<div><p>a</p><details>d</details></div>', 'p', 0, true];
        yield 'p before dialog'     => ['<div><p>a</p><dialog>d</dialog></div>', 'p', 0, true];
        yield 'p before figcaption' => ['<figure><p>a</p><figcaption>c</figcaption></figure>', 'p', 0, true];
        yield 'p before figure'     => ['<div><p>a</p><figure>f</figure></div>', 'p', 0, true];
        yield 'p before main'       => ['<div><p>a</p><main>m</main></div>', 'p', 0, true];
        yield 'p before search'     => ['<div><p>a</p><search>s</search></div>', 'p', 0, true];

        // thead — followed by tbody or tfoot (no end-of-parent clause).
        yield 'thead before tbody' => ['<table><thead><tr><th>h</th></tr></thead><tbody><tr><td>b</td></tr></tbody></table>', 'thead', 0, true];
        yield 'thead before tfoot' => ['<table><thead><tr><th>h</th></tr></thead><tfoot><tr><td>f</td></tr></tfoot></table>', 'thead', 0, true];
        yield 'thead at end'       => ['<table><thead><tr><th>h</th></tr></thead></table>', 'thead', 0, false];

        // tbody — followed by tbody or tfoot, or end of parent.
        yield 'tbody before tfoot' => ['<table><tbody><tr><td>b</td></tr></tbody><tfoot><tr><td>f</td></tr></tfoot></table>', 'tbody', 0, true];
        yield 'tbody at end'       => ['<table><tbody><tr><td>b</td></tr></tbody></table>', 'tbody', 0, true];

        // tfoot — only at end of parent.
        yield 'tfoot at end'       => ['<table><tbody><tr><td>b</td></tr></tbody><tfoot><tr><td>f</td></tr></tfoot></table>', 'tfoot', 0, true];
        yield 'tfoot before tbody' => ['<table><tfoot><tr><td>f</td></tr></tfoot><tbody><tr><td>b</td></tr></tbody></table>', 'tfoot', 0, false];
    }

    /**
     * caption / colgroup omit their end tag unless immediately followed by ASCII
     * whitespace or a comment. Built with explicit DOM nodes so the raw next
     * sibling is deterministic (libxml's table parser otherwise normalizes the
     * inter-element whitespace).
     *
     * @param 'caption'|'colgroup' $tag
     */
    #[DataProvider('provideCaptionColgroupEndTagOmissionCases')]
    public function testCaptionColgroupEndTagOmission(string $tag, string $nextKind, bool $expected): void
    {
        $doc = new DOMDocument();
        $table = $doc->createElement('table');
        $element = $doc->createElement($tag);
        $table->appendChild($element);

        $next = match ($nextKind) {
            'element'    => $doc->createElement('tbody'),
            'whitespace' => $doc->createTextNode(' x'),
            'text'       => $doc->createTextNode('x'),
            'comment'    => $doc->createComment('c'),
            default      => null,
        };
        if ($next !== null) {
            $table->appendChild($next);
        }
        $doc->appendChild($table);

        self::assertSame($expected, (new OptionalTagOmission())->isOptional($element));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function provideCaptionColgroupEndTagOmissionCases(): iterable
    {
        foreach (['caption', 'colgroup'] as $tag) {
            yield "{$tag} before element"    => [$tag, 'element', true];
            yield "{$tag} at end"            => [$tag, 'none', true];
            yield "{$tag} before text"       => [$tag, 'text', true];
            yield "{$tag} before whitespace" => [$tag, 'whitespace', false];
            yield "{$tag} before comment"    => [$tag, 'comment', false];
        }
    }

    /**
     * option before `<hr>` (the `<select>` separator addition) — built with
     * explicit DOM nodes because libxml's HTML4-era parser relocates an `<hr>`
     * out of `<select>`, so a loadHTML-based case would pass for the wrong
     * reason (option-adjacent-to-option) and leave the `hr` arm untested.
     */
    public function testOptionEndTagOmittableBeforeHr(): void
    {
        $doc = new DOMDocument();
        $select = $doc->createElement('select');
        $option = $doc->createElement('option');
        $option->appendChild($doc->createTextNode('1'));
        $select->appendChild($option);
        $select->appendChild($doc->createElement('hr'));
        $doc->appendChild($select);

        self::assertTrue((new OptionalTagOmission())->isOptional($option));
    }

    /**
     * WHATWG end-tag conditions for the structural tags: `html` and `body` may
     * omit their end tag unless immediately followed by a comment; `head`
     * additionally not when followed by ASCII whitespace. Built with explicit
     * DOM nodes so the raw next sibling is deterministic.
     *
     * @param 'html'|'head'|'body' $tag
     */
    #[DataProvider('provideStructuralEndTagOmissionCases')]
    public function testStructuralEndTagOmission(string $tag, string $nextKind, bool $expected): void
    {
        $doc = new DOMDocument();

        if ($tag === 'html') {
            $element = $doc->createElement('html');
            $doc->appendChild($element);
            if ($nextKind === 'comment') {
                $doc->appendChild($doc->createComment('c'));
            }
        } else {
            $html = $doc->createElement('html');
            $element = $doc->createElement($tag);
            $html->appendChild($element);
            $next = match ($nextKind) {
                'comment'    => $doc->createComment('c'),
                'whitespace' => $doc->createTextNode(' '),
                'element'    => $doc->createElement('section'),
                default      => null,
            };
            if ($next !== null) {
                $html->appendChild($next);
            }
            $doc->appendChild($html);
        }

        self::assertSame($expected, (new OptionalTagOmission())->isOptional($element));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function provideStructuralEndTagOmissionCases(): iterable
    {
        yield 'html at end'            => ['html', 'none', true];
        yield 'html before comment'    => ['html', 'comment', false];
        yield 'body at end'            => ['body', 'none', true];
        yield 'body before comment'    => ['body', 'comment', false];
        yield 'body before whitespace' => ['body', 'whitespace', true];
        yield 'head before element'    => ['head', 'element', true];
        yield 'head at end'            => ['head', 'none', true];
        // Deliberate deviation: the spec blocks head-omission before ASCII
        // whitespace too, but the reparse difference is a rendering-irrelevant
        // whitespace node moving into head, and honoring it would keep
        // `</head>` on virtually every real-world page.
        yield 'head before whitespace (deviation, still true)' => ['head', 'whitespace', true];
        yield 'head before comment'    => ['head', 'comment', false];
    }

    /**
     * Full-pipeline proof: a preserved comment right after `</body>` blocks the
     * end-tag omission (otherwise a reparse would pull the comment inside body,
     * changing the DOM).
     */
    public function testBodyEndTagKeptWhenFollowedByPreservedComment(): void
    {
        $out = (new HtmlMin())->doRemoveComments(false)
            ->minify('<html><head><title>t</title></head><body>x</body><!--after--></html>');

        self::assertStringContainsString('</body><!--after-->', $out);
    }

    /**
     * Full-pipeline proof that the new table end-tag rules actually drop the
     * closing tags from minified output.
     *
     * @param string[] $absent
     */
    #[DataProvider('provideTableEndTagsOmittedInOutputCases')]
    public function testTableEndTagsOmittedInOutput(string $html, array $absent): void
    {
        $output = (new HtmlMin())->minify($html);

        foreach ($absent as $tag) {
            self::assertStringNotContainsString($tag, $output, "expected {$tag} to be omitted");
        }
    }

    /**
     * @return iterable<string, array{string, string[]}>
     */
    public static function provideTableEndTagsOmittedInOutputCases(): iterable
    {
        yield 'thead+tbody' => [
            '<table><thead><tr><th>h</th></tr></thead><tbody><tr><td>b</td></tr></tbody></table>',
            ['</thead>', '</tbody>', '</tr>', '</th>', '</td>'],
        ];
        yield 'tfoot at end' => [
            '<table><tbody><tr><td>b</td></tr></tbody><tfoot><tr><td>f</td></tr></tfoot></table>',
            ['</tbody>', '</tfoot>'],
        ];
    }

    /**
     * isStartOptional truth table for html/head/body across first-child kinds
     * and the attribute guard. Built with explicit DOM nodes so the first child
     * is deterministic.
     *
     * @param 'html'|'head'|'body'|'div'              $tag
     * @param 'none'|'comment'|'element'|'script'|'whitespace'|'text' $firstChild
     */
    #[DataProvider('provideStartTagOmittableCases')]
    public function testStartTagOmittable(string $tag, string $firstChild, bool $withAttribute, bool $expected): void
    {
        $doc = new DOMDocument();
        $element = $doc->createElement($tag);
        if ($withAttribute) {
            $element->setAttribute('id', 'x');
        }
        $child = match ($firstChild) {
            'comment'    => $doc->createComment('c'),
            'element'    => $doc->createElement('div'),
            'script'     => $doc->createElement('script'),
            'whitespace' => $doc->createTextNode(' x'),
            'text'       => $doc->createTextNode('x'),
            default      => null,
        };
        if ($child !== null) {
            $element->appendChild($child);
        }
        $doc->appendChild($element);

        self::assertSame($expected, (new OptionalTagOmission())->isStartOptional($element));
    }

    /**
     * @return iterable<string, array{string, string, bool, bool}>
     */
    public static function provideStartTagOmittableCases(): iterable
    {
        // html — omittable unless first child is a comment; never with attributes.
        yield 'html empty'        => ['html', 'none', false, true];
        yield 'html comment'      => ['html', 'comment', false, false];
        yield 'html element'      => ['html', 'element', false, true];
        yield 'html text'         => ['html', 'text', false, true];
        yield 'html with attr'    => ['html', 'element', true, false];

        // head — omittable when empty or when first child is an element.
        yield 'head empty'        => ['head', 'none', false, true];
        yield 'head element'      => ['head', 'element', false, true];
        yield 'head text'         => ['head', 'text', false, false];
        yield 'head whitespace'   => ['head', 'whitespace', false, false];
        yield 'head with attr'    => ['head', 'element', true, false];

        // body — omittable when empty, or first child is neither whitespace nor a
        // comment nor a meta/link/script/style/template element.
        yield 'body empty'        => ['body', 'none', false, true];
        yield 'body comment'      => ['body', 'comment', false, false];
        yield 'body whitespace'   => ['body', 'whitespace', false, false];
        yield 'body text'         => ['body', 'text', false, true];
        yield 'body element'      => ['body', 'element', false, true];
        yield 'body script first' => ['body', 'script', false, false];
        yield 'body with attr'    => ['body', 'element', true, false];

        // non-structural tag — never start-optional.
        yield 'div'               => ['div', 'element', false, false];
    }

    public function testStartTagOmissionIsOffByDefault(): void
    {
        $output = (new HtmlMin())->minify(
            '<!doctype html><html><head><title>x</title></head><body><p>hi</p></body></html>',
        );

        self::assertStringContainsString('<html>', $output);
        self::assertStringContainsString('<head>', $output);
        self::assertStringContainsString('<body>', $output);
    }

    public function testStartTagOmissionWhenEnabled(): void
    {
        $minifier = (new HtmlMin())->doRemoveOmittedHtmlStartTags(true);
        $output = $minifier->minify(
            '<!doctype html><html><head><title>x</title></head><body><p>hi</p></body></html>',
        );

        self::assertStringNotContainsString('<html>', $output);
        self::assertStringNotContainsString('<head>', $output);
        self::assertStringNotContainsString('<body>', $output);
        self::assertStringContainsString('<title>x</title>', $output);
        self::assertStringContainsString('<p>hi', $output);
    }

    public function testStartTagWithAttributesKeptWhenEnabled(): void
    {
        $minifier = (new HtmlMin())->doRemoveOmittedHtmlStartTags(true);
        $output = $minifier->minify(
            '<!doctype html><html lang="en"><head><title>x</title></head><body><script>var a=1</script><p>hi</p></body></html>',
        );

        // <html> kept (has lang), <body> kept (first child is a script element).
        self::assertStringContainsString('<html lang=en>', $output);
        self::assertStringContainsString('<body>', $output);
        self::assertStringNotContainsString('<head>', $output);
    }

    public function testEnabledViaMinifierOptions(): void
    {
        $minifier = new HtmlMin(new \Akankov\HtmlMin\Config\MinifierOptions(removeOmittedHtmlStartTags: true));

        self::assertTrue($minifier->isDoRemoveOmittedHtmlStartTags());
        self::assertStringNotContainsString(
            '<body>',
            $minifier->minify('<!doctype html><html><head><title>x</title></head><body><p>hi</p></body></html>'),
        );
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

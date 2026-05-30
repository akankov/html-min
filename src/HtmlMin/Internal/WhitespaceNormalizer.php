<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Internal;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use SplObjectStorage;

use const XML_TEXT_NODE;

/**
 * Whitespace-collapsing DOM passes.
 *
 * Pure transformations on a parsed document — they read no minifier config (the
 * toggles that enable them are checked at the call site), only the static tag
 * tables below. Extracted from HtmlMin so the whitespace logic lives on its own.
 */
class WhitespaceNormalizer
{
    private const string SPACE_PATTERN = "/[[:space:]]{2,}|[\r\n]/u";

    /**
     * Tags whose adjacent/inner text nodes get their whitespace collapsed.
     *
     * @var array<string, string>
     */
    private const array TRIM_AROUND_TAGS = [
        'article' => '',
        'br'      => '',
        'div'     => '',
        'footer'  => '',
        'hr'      => '',
        'nav'     => '',
        'p'       => '',
        'script'  => '',
    ];

    /**
     * Tags whose descendant text is whitespace-protected (never collapsed).
     *
     * @var array<string, string>
     */
    private const array SKIP_TAGS = [
        'code'     => '',
        'pre'      => '',
        'script'   => '',
        'style'    => '',
        'textarea' => '',
    ];

    /**
     * Collapse whitespace in the text nodes immediately around a tag.
     */
    public static function removeAroundTags(DOMElement $element): void
    {
        if (isset(self::TRIM_AROUND_TAGS[$element->tagName])) {
            /** @var array<?DOMNode> $candidates */
            $candidates = [];
            if ($element->childNodes->length > 0) {
                $candidates[] = $element->firstChild;
                $candidates[] = $element->lastChild;
                $candidates[] = $element->previousSibling;
                $candidates[] = $element->nextSibling;
            }

            foreach ($candidates as $candidate) {
                if ($candidate === null || $candidate->nodeType !== XML_TEXT_NODE) {
                    continue;
                }

                $nodeValueTmp = preg_replace(self::SPACE_PATTERN, ' ', (string) $candidate->nodeValue);
                if ($nodeValueTmp !== null) {
                    $candidate->nodeValue = $nodeValueTmp;
                }
            }
        }
    }

    /**
     * Collapse runs of whitespace in every text node, except those inside a
     * whitespace-protected ancestor (`<pre>`, `<code>`, …).
     */
    public static function sumUp(DOMDocument $dom): void
    {
        // Walking the parent chain per text node re-traverses the same ancestors
        // thousands of times on large docs. Instead, pre-compute the set of text
        // nodes that live inside any whitespace-protected ancestor and check via
        // O(1) lookup during the main pass.
        // findAll() with a tag selector yields elements; with //text() it yields
        // text nodes — so no instanceof narrowing is needed here.
        /** @var SplObjectStorage<DOMNode, null> $protected */
        $protected = new SplObjectStorage();
        $skipSelector = implode(', ', array_keys(self::SKIP_TAGS));
        foreach (HtmlParser::findAll($dom, $skipSelector) as $protectedAncestor) {
            self::collectDescendantTextNodes($protectedAncestor, $protected);
        }

        foreach (HtmlParser::findAll($dom, '//text()') as $text_node) {
            if ($protected->offsetExists($text_node)) {
                continue;
            }

            $nodeValueTmp = preg_replace(self::SPACE_PATTERN, ' ', $text_node->nodeValue ?? '');
            if ($nodeValueTmp !== null) {
                $text_node->nodeValue = $nodeValueTmp;
            }
        }
    }

    /**
     * @param SplObjectStorage<DOMNode, null> $store
     */
    private static function collectDescendantTextNodes(DOMNode $node, SplObjectStorage $store): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $store[$child] = null;
            } elseif ($child->hasChildNodes()) {
                self::collectDescendantTextNodes($child, $store);
            }
        }
    }
}

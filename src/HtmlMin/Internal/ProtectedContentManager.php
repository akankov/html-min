<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Internal;

use Akankov\HtmlMin\Contract\HtmlMinInterface;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMText;
use Psr\Log\LoggerInterface;

/**
 * Protects content that must survive the DOM walk untouched — `<nocompress>`
 * and `<code>` subtrees, inline `<script>`/`<style>` bodies (optionally
 * inline-minified first), and conditional / special comments — by swapping it
 * for placeholder tokens that {@see \Akankov\HtmlMin\HtmlMin} restores after
 * serialization.
 *
 * Extracted from HtmlMin: it owns the protected-content state (the saved-node
 * map, the counter, and the placeholder token) that used to live on the
 * god-class. Reads behaviour flags through {@see HtmlMinInterface} and shares
 * HtmlMin's {@see InlineContentMinifier} instance.
 */
final class ProtectedContentManager
{
    /**
     * Placeholder element name standing in for protected content.
     *
     * Load-bearing literal: this tag name is inserted into a DOMElement
     * nodeValue / DOMText and the post-serialize regex expects to find it raw
     * in the output. Shorter `htmlmin-*` forms can change how libxml serializes
     * the surrounding text-vs-markup boundary and leave the placeholder
     * entity-escaped, which silently breaks restoration.
     */
    private const string PLACEHOLDER_TAG = 'html-min--protected--saved-content';

    /** @var array<int, string> */
    private array $savedContent = [];

    private int $counter = 0;

    public function __construct(
        private readonly HtmlMinInterface $config,
        private readonly InlineContentMinifier $inlineContentMinifier,
    ) {
    }

    /** Reset per-minify()-run state. */
    public function reset(): void
    {
        $this->savedContent = [];
        $this->counter = 0;
    }

    /** The placeholder element name — HtmlMin builds the restore regex from it. */
    public function placeholderTag(): string
    {
        return self::PLACEHOLDER_TAG;
    }

    /** Protect `<nocompress>` subtrees (runs before the observer pass). */
    public function protectNoCompress(DOMDocument $dom): void
    {
        $this->protectTagHelper($dom, 'nocompress', true);
    }

    /**
     * Protect `<code>`, inline `<script>`/`<style>`, and conditional / special
     * comments (runs after the observer pass).
     *
     * @param string[] $specialCommentsStartingWith
     * @param string[] $specialCommentsEndingWith
     */
    public function protect(
        DOMDocument $dom,
        array $specialCommentsStartingWith,
        array $specialCommentsEndingWith,
        ?LoggerInterface $logger,
    ): void {
        $this->protectTagHelper($dom, 'code');

        $didRemoveComments = false;

        foreach (HtmlParser::findAll($dom, 'script, style') as $element) {
            // findAll('script, style') yields parented elements.
            if ($element instanceof DOMElement && $element->parentNode !== null) {
                if ($element->tagName === 'script' || $element->tagName === 'style') {
                    $attributes = HtmlParser::getAllAttributes($element);
                    // skip external links
                    if (isset($attributes['src'])) {
                        continue;
                    }
                }

                // Protected <script>/<style> content keeps internal whitespace, while
                // leading and trailing padding is stripped before serialization.
                $inner = HtmlParser::innerHtml($element);
                if ($element->tagName === 'script' || $element->tagName === 'style') {
                    $inner = trim($inner);
                    $inner = $this->inlineContentMinifier->process(
                        $element,
                        $inner,
                        $this->config->isDoMinifyInlineCss(),
                        $this->config->isDoMinifyInlineJs(),
                        $logger,
                    );
                }
                $this->savedContent[$this->counter] = $inner;
                $element->nodeValue = $this->placeholder();

                ++$this->counter;
            }
        }

        foreach (HtmlParser::findAll($dom, '//comment()') as $element) {
            // findAll('//comment()') yields parented comment nodes.
            if ($element instanceof DOMComment && $element->parentNode !== null) {
                $text = $element->textContent;

                if (
                    !self::isConditionalComment($text)
                    &&
                    !self::isSpecialComment($text, $specialCommentsStartingWith, $specialCommentsEndingWith)
                ) {
                    if ($this->config->isDoRemoveComments() && !str_contains($text, '[')) {
                        $parentNode = $element->parentNode;
                        $parentNode->removeChild($element);
                        $didRemoveComments = true;
                    }

                    continue;
                }

                $this->savedContent[$this->counter] = '<!--' . $text . '-->';

                $child = new DOMText($this->placeholder());
                $parentNode = $element->parentNode;
                $parentNode->replaceChild($child, $element);

                ++$this->counter;
            }
        }

        if ($didRemoveComments) {
            $dom->normalizeDocument();
        }
    }

    /**
     * preg_replace_callback callback that swaps a placeholder back for the
     * protected content it stands in for.
     *
     * @param array<int|string, string> $matches PREG matches
     */
    public function restore(array $matches): string
    {
        return preg_match('/=(?:"|)?(\d+)(?:"|)?/', str_replace("'", "\a", (string) $matches['attributes']), $matchesInner) === 1
            ? ($this->savedContent[(int) $matchesInner[1]] ?? '')
            : '';
    }

    private function protectTagHelper(DOMDocument $dom, string $selector, bool $useElementScope = false): void
    {
        foreach (HtmlParser::findAll($dom, $selector) as $element) {
            // findAll yields elements; a parsed element always has a parent.
            if ($element instanceof DOMElement && $element->parentNode !== null) {
                $placeholder = $this->placeholder();

                if ($useElementScope) {
                    // Replace only the matched element's inner content with the
                    // placeholder. The element itself stays in the DOM, so its
                    // siblings continue through normal minification.
                    $this->savedContent[$this->counter] = HtmlParser::innerHtml($element);
                    $element->nodeValue = $placeholder;
                } else {
                    $parentNode = $element->parentNode;
                    if ($parentNode->nodeValue !== null) {
                        $this->savedContent[$this->counter] = $parentNode instanceof DOMElement ? HtmlParser::innerHtml($parentNode) : '';
                        $parentNode->nodeValue = $placeholder;
                    }
                }

                ++$this->counter;
            }
        }
    }

    private function placeholder(): string
    {
        return '<' . self::PLACEHOLDER_TAG . ' data-' . self::PLACEHOLDER_TAG . '="' . $this->counter . '"></' . self::PLACEHOLDER_TAG . '>';
    }

    private static function isConditionalComment(string $comment): bool
    {
        if (str_contains($comment, '[if ')) {
            /** @noinspection RegExpRedundantEscape */
            /** @noinspection NestedPositiveIfStatementsInspection */
            if (preg_match('/^\[if [^\]]+\]/', $comment)) {
                return true;
            }
        }

        if (str_contains($comment, '[endif]')) {
            /** @noinspection RegExpRedundantEscape */
            /** @noinspection NestedPositiveIfStatementsInspection */
            if (preg_match('/\[endif\]$/', $comment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $startingWith
     * @param string[] $endingWith
     */
    private static function isSpecialComment(string $comment, array $startingWith, array $endingWith): bool
    {
        foreach ($startingWith as $search) {
            if (str_starts_with($comment, $search)) {
                return true;
            }
        }

        foreach ($endingWith as $search) {
            if (str_ends_with($comment, $search)) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Internal;

use DOMElement;
use DOMNode;
use DOMText;

/**
 * HTML5 optional-end-tag rules.
 *
 * Decides whether an element's closing tag may be omitted per the WHATWG
 * tag-omission spec (https://html.spec.whatwg.org/multipage/syntax.html#syntax-tag-omission).
 * Extracted from HtmlMin so the rule set lives — and can be tested — on its own.
 */
class OptionalTagOmission
{
    /**
     * Tags whose end tag may always be omitted.
     *
     * @var string[]
     */
    private const array OPTIONAL_END_TAGS = [
        'html',
        'head',
        'body',
    ];

    /**
     * Tags whose end tag may be omitted only conditionally (depending on the
     * next sibling and/or parent). Used as a fast-path: any tag not in
     * OPTIONAL_END_TAGS and not here can never have its end tag omitted, so we
     * can short-circuit before paying the next-sibling traversal.
     *
     * @var string[]
     */
    private const array CONDITIONAL_END_TAGS = [
        'li',
        'optgroup',
        'rp',
        'tr',
        'source',
        'td',
        'th',
        'dd',
        'dt',
        'option',
        'p',
    ];

    /**
     * Elements a `<source>` may sit in for its end tag to be omittable.
     *
     * @var string[]
     */
    private const array SOURCE_PARENTS = [
        'audio',
        'video',
        'picture',
        'source',
    ];

    /**
     * Block-level elements that, when they immediately follow a `<p>`, allow the
     * `<p>` end tag to be omitted.
     *
     * @var string[]
     */
    private const array P_FOLLOWED_BY = [
        'address',
        'article',
        'aside',
        'blockquote',
        'dir',
        'div',
        'dl',
        'fieldset',
        'footer',
        'form',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'header',
        'hgroup',
        'hr',
        'menu',
        'nav',
        'ol',
        'p',
        'pre',
        'section',
        'table',
        'ul',
    ];

    /**
     * Parents in which a trailing `<p>` (no following sibling) keeps its end tag.
     *
     * @var string[]
     */
    private const array P_TRAILING_DISALLOWED_PARENTS = [
        'a',
        'audio',
        'del',
        'ins',
        'map',
        'noscript',
        'video',
    ];

    /**
     * Memoised results keyed by "$tag|$parent|$next-sibling-marker". The
     * boolean result is a pure function of those names, so the cache survives
     * across minify() calls on the owning HtmlMin instance.
     *
     * @var array<string, bool>
     */
    private array $cache = [];

    public function isOptional(DOMNode $node): bool
    {
        $tag_name = $node->nodeName;

        if (\in_array($tag_name, self::OPTIONAL_END_TAGS, true)) {
            return true;
        }

        if (!\in_array($tag_name, self::CONDITIONAL_END_TAGS, true)) {
            return false;
        }

        /** @var DOMNode|null $parent_node - false-positive error from phpstan */
        $parent_node = $node->parentNode;

        if ($parent_node) {
            $parent_tag_name = $parent_node->nodeName;
        } else {
            $parent_tag_name = null;
        }

        $nextSibling = self::nextSiblingElement($node);

        $next_marker = match (true) {
            $nextSibling === null              => '_NULL_',
            $nextSibling instanceof DOMElement => 'E:' . $nextSibling->tagName,
            default                            => '_OTHER_',
        };
        $cache_key = $tag_name . '|' . ($parent_tag_name ?? '_NONE_') . '|' . $next_marker;
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $result = $this->conditionalEndTagOptional($tag_name, $node, $parent_tag_name, $nextSibling);

        $this->cache[$cache_key] = $result;

        return $result;
    }

    /**
     * Per-tag conditional end-tag rules. The caller guarantees `$tag` is one of
     * {@see CONDITIONAL_END_TAGS}, so the match is exhaustive in practice (hence
     * no default arm — adding one would be dead code under the 100% line floor).
     *
     * Spec note: `dt` is grouped with `dd` (both treated as omittable at end of
     * parent). That is a deliberate, output-safe deviation kept verbatim from the
     * original boolean and locked by OptionalTagOmissionTest — the spec only lets
     * `dt` omit when followed by `dt`/`dd`, but omitting it at end-of-parent still
     * produces valid, equivalently-parsed HTML.
     */
    private function conditionalEndTagOptional(string $tag, DOMNode $node, ?string $parentTag, ?DOMNode $next): bool
    {
        // @phpstan-ignore match.unhandled (exhaustive over CONDITIONAL_END_TAGS)
        return match ($tag) {
            'li'       => self::followedByOrEndOfParent($next, 'li'),
            'optgroup' => self::followedByOrEndOfParent($next, 'optgroup'),
            'rp'       => self::followedByOrEndOfParent($next, 'rp', 'rt'),
            'tr'       => self::followedByOrEndOfParent($next, 'tr'),
            'td', 'th' => self::followedByOrEndOfParent($next, 'td', 'th'),
            'dd', 'dt' => self::followedByOrEndOfParent($next, 'dd', 'dt'),
            'option'   => self::followedByOrEndOfParent($next, 'option', 'optgroup'),
            'source'   => \in_array($parentTag, self::SOURCE_PARENTS, true)
                          && self::followedByOrEndOfParent($next, 'source'),
            'p'        => self::pEndTagOptional($node, $next),
        };
    }

    /**
     * The shared "followed by one of $tags, or no more content in the parent"
     * shape used by most conditional end-tag rules.
     */
    private static function followedByOrEndOfParent(?DOMNode $next, string ...$tags): bool
    {
        if ($next === null) {
            return true;
        }

        return $next instanceof DOMElement && \in_array($next->tagName, $tags, true);
    }

    private static function pEndTagOptional(DOMNode $node, ?DOMNode $next): bool
    {
        if ($next === null) {
            $parent = $node->parentNode;

            return $parent !== null
                   && !\in_array($parent->nodeName, self::P_TRAILING_DISALLOWED_PARENTS, true);
        }

        return $next instanceof DOMElement && \in_array($next->tagName, self::P_FOLLOWED_BY, true);
    }

    /**
     * The next sibling that is a DOMElement (or a meaningful DOMText), skipping
     * insignificant whitespace-only text nodes.
     */
    public static function nextSiblingElement(DOMNode $node): ?DOMNode
    {
        do {
            /** @var DOMElement|DOMText|null $nodeTmp - false-positive error from phpstan */
            $nodeTmp = $node->nextSibling;

            if ($nodeTmp instanceof DOMText) {
                if (
                    trim($nodeTmp->textContent) !== ''
                    &&
                    !str_contains($nodeTmp->textContent, '<')
                ) {
                    $node = $nodeTmp;
                } else {
                    $node = $nodeTmp->nextSibling;
                }
            } else {
                $node = $nodeTmp;
            }
        } while (!($node === null || $node instanceof DOMElement || $node instanceof DOMText));

        return $node;
    }
}

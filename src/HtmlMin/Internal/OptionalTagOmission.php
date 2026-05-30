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
 * Extracted from HtmlMin so the 280-line rule set lives — and can be tested —
 * on its own.
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

        // https://html.spec.whatwg.org/multipage/syntax.html#syntax-tag-omission

        // Implemented:
        //
        // A <p> element's end tag may be omitted if the p element is immediately followed by an address, article, aside, blockquote, details, div, dl, fieldset, figcaption, figure, footer, form, h1, h2, h3, h4, h5, h6, header, hgroup, hr, main, menu, nav, ol, p, pre, section, table, or ul element, or if there is no more content in the parent element and the parent element is an HTML element that is not an a, audio, del, ins, map, noscript, or video element, or an autonomous custom element.
        // An <li> element's end tag may be omitted if the li element is immediately followed by another li element or if there is no more content in the parent element.
        // A <td> element's end tag may be omitted if the td element is immediately followed by a td or th element, or if there is no more content in the parent element.
        // An <option> element's end tag may be omitted if the option element is immediately followed by another option element, or if it is immediately followed by an optgroup element, or if there is no more content in the parent element.
        // A <tr> element's end tag may be omitted if the tr element is immediately followed by another tr element, or if there is no more content in the parent element.
        // A <th> element's end tag may be omitted if the th element is immediately followed by a td or th element, or if there is no more content in the parent element.
        // A <dt> element's end tag may be omitted if the dt element is immediately followed by another dt element or a dd element.
        // A <dd> element's end tag may be omitted if the dd element is immediately followed by another dd element or a dt element, or if there is no more content in the parent element.
        // An <rp> element's end tag may be omitted if the rp element is immediately followed by an rt or rp element, or if there is no more content in the parent element.
        // An <optgroup> element's end tag may be omitted if the optgroup element is immediately followed by another optgroup element, or if there is no more content in the parent element.

        /**
         * @noinspection TodoComment
         *
         * TODO: Not Implemented
         */
        //
        // <html> may be omitted if first thing inside is not comment
        // <head> may be omitted if first thing inside is an element
        // <body> may be omitted if first thing inside is not space, comment, <meta>, <link>, <script>, <style> or <template>
        // <colgroup> may be omitted if first thing inside is <col>
        // <tbody> may be omitted if first thing inside is <tr>
        // A <colgroup> element's start tag may be omitted if the first thing inside the colgroup element is a col element, and if the element is not immediately preceded by another colgroup element whose end tag has been omitted. (It can't be omitted if the element is empty.)
        // A <colgroup> element's end tag may be omitted if the colgroup element is not immediately followed by ASCII whitespace or a comment.
        // A <caption> element's end tag may be omitted if the caption element is not immediately followed by ASCII whitespace or a comment.
        // A <thead> element's end tag may be omitted if the thead element is immediately followed by a tbody or tfoot element.
        // A <tbody> element's start tag may be omitted if the first thing inside the tbody element is a tr element, and if the element is not immediately preceded by a tbody, thead, or tfoot element whose end tag has been omitted. (It can't be omitted if the element is empty.)
        // A <tbody> element's end tag may be omitted if the tbody element is immediately followed by a tbody or tfoot element, or if there is no more content in the parent element.
        // A <tfoot> element's end tag may be omitted if there is no more content in the parent element.
        //
        // <-- However, a start tag must never be omitted if it has any attributes.

        $result = (
            $tag_name === 'li'
                   &&
                   (
                       $nextSibling === null
                       ||
                       (
                           $nextSibling instanceof DOMElement
                           &&
                           $nextSibling->tagName === 'li'
                       )
                   )
        )
               ||
               (
                   $tag_name === 'optgroup'
                   &&
                   (
                       $nextSibling === null
                       ||
                       (
                           $nextSibling instanceof DOMElement
                           &&
                           $nextSibling->tagName === 'optgroup'
                       )
                   )
               )
               ||
               (
                   $tag_name === 'rp'
                   &&
                   (
                       $nextSibling === null
                       ||
                       (
                           $nextSibling instanceof DOMElement
                           &&
                           (
                               $nextSibling->tagName === 'rp'
                               ||
                               $nextSibling->tagName === 'rt'
                           )
                       )
                   )
               )
               ||
               (
                   $tag_name === 'tr'
                   &&
                   (
                       $nextSibling === null
                       ||
                       (
                           $nextSibling instanceof DOMElement
                           &&
                           $nextSibling->tagName === 'tr'
                       )
                   )
               )
               ||
               (
                   $tag_name === 'source'
                   &&
                   (
                       $parent_tag_name === 'audio'
                       ||
                       $parent_tag_name === 'video'
                       ||
                       $parent_tag_name === 'picture'
                       ||
                       $parent_tag_name === 'source'
                   )
                   &&
                   (
                       $nextSibling === null
                       ||
                       (
                           $nextSibling instanceof DOMElement
                           &&
                           $nextSibling->tagName === 'source'
                       )
                   )
               )
               ||
               (
                   (
                       $tag_name === 'td'
                       ||
                       $tag_name === 'th'
                   )
                   &&
                   (
                       $nextSibling === null
                       ||
                       (
                           $nextSibling instanceof DOMElement
                           &&
                           (
                               $nextSibling->tagName === 'td'
                               ||
                               $nextSibling->tagName === 'th'
                           )
                       )
                   )
               )
               ||
               (
                   (
                       $tag_name === 'dd'
                       ||
                       $tag_name === 'dt'
                   )
                   &&
                   (
                       $nextSibling === null
                       ||
                       (
                           $nextSibling instanceof DOMElement
                           &&
                           (
                               $nextSibling->tagName === 'dd'
                               ||
                               $nextSibling->tagName === 'dt'
                           )
                       )
                   )
               )
               ||
               (
                   $tag_name === 'option'
                   &&
                   (
                       $nextSibling === null
                       ||
                       (
                           $nextSibling instanceof DOMElement
                           &&
                           (
                               $nextSibling->tagName === 'option'
                               ||
                               $nextSibling->tagName === 'optgroup'
                           )
                       )
                   )
               )
               ||
               (
                   $tag_name === 'p'
                   &&
                   (
                       (
                           $nextSibling === null
                           &&
                           $node->parentNode !== null
                           &&
                           !\in_array(
                               $node->parentNode->nodeName,
                               [
                                   'a',
                                   'audio',
                                   'del',
                                   'ins',
                                   'map',
                                   'noscript',
                                   'video',
                               ],
                               true,
                           )
                       )
                       ||
                       (
                           $nextSibling instanceof DOMElement
                           &&
                           \in_array(
                               $nextSibling->tagName,
                               [
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
                               ],
                               true,
                           )
                       )
                   )
               );

        $this->cache[$cache_key] = $result;

        return $result;
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

<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Internal;

use Akankov\HtmlMin\Contract\HtmlMinInterface;
use DOMAttr;
use DOMComment;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Serializes a minified DOM tree back to an HTML5 string.
 *
 * Reads its behaviour flags through {@see HtmlMinInterface} (the same config
 * contract observers use) and defers optional-end-tag decisions to
 * {@see OptionalTagOmission}. Extracted from HtmlMin so the DOM-walk /
 * whitespace / attribute-string logic lives on its own.
 */
class DomSerializer
{
    private const string UNQUOTED_ATTRIBUTE_VALUE_FORBIDDEN_CHARS = "\"'=<>` \t\r\n\f";

    /** Mirrors HtmlMin's whitespace-collapse pattern (used for srcset/sizes). */
    private const string SPACE_PATTERN = "/[[:space:]]{2,}|[\r\n]/u";

    /** @var array<string, string> */
    private const array BOOLEAN_ATTRIBUTES = [
        'allowfullscreen' => '',
        'async'           => '',
        'autofocus'       => '',
        'autoplay'        => '',
        'checked'         => '',
        'compact'         => '',
        'controls'        => '',
        'declare'         => '',
        'default'         => '',
        'defaultchecked'  => '',
        'defaultmuted'    => '',
        'defaultselected' => '',
        'defer'           => '',
        'disabled'        => '',
        'enabled'         => '',
        'formnovalidate'  => '',
        'hidden'          => '',
        'indeterminate'   => '',
        'inert'           => '',
        'ismap'           => '',
        'itemscope'       => '',
        'loop'            => '',
        'multiple'        => '',
        'muted'           => '',
        'nohref'          => '',
        'noresize'        => '',
        'noshade'         => '',
        'novalidate'      => '',
        'nowrap'          => '',
        'open'            => '',
        'pauseonexit'     => '',
        'readonly'        => '',
        'required'        => '',
        'reversed'        => '',
        'scoped'          => '',
        'seamless'        => '',
        'selected'        => '',
        'sortable'        => '',
        'truespeed'       => '',
        'typemustmatch'   => '',
        'visible'         => '',
    ];

    public function __construct(
        private readonly HtmlMinInterface $config,
        private readonly OptionalTagOmission $optionalTagOmission,
    ) {
    }

    public function toString(DOMNode $node): string
    {
        // Collect per-level output into a parts array and join once at the end:
        // avoids the quadratic cost of `$html .= $chunk` on the ~30 KB output of
        // large documents (wikipedia fixture). Whitespace handling that used to
        // call `rtrim($html)` / `str_ends_with($html, ' ')` on the growing
        // accumulator is delegated to partsEndWithSpace() / rtrimParts(), which
        // keep the same semantics since every append is either whitespace-only
        // or ends with non-whitespace — whitespace runs never straddle parts.
        $parts = [];
        $emptyStringTmp = '';

        foreach ($node->childNodes as $child) {
            if ($emptyStringTmp === 'is_empty') {
                $emptyStringTmp = 'last_was_empty';
            } else {
                $emptyStringTmp = '';
            }

            if ($child instanceof DOMElement) {
                $isHtml5 = !$this->config->isHTML4() && !$this->config->isXHTML();
                $omitEndTags = $isHtml5 && $this->config->isDoRemoveOmittedHtmlTags();
                // Start-tag omission is a separate opt-in — far more aggressive
                // than end-tag omission (can blank out an empty document).
                $omitStartTags = $isHtml5 && $this->config->isDoRemoveOmittedHtmlStartTags();

                if ($omitStartTags && $this->optionalTagOmission->isStartOptional($child)) {
                    // Start tag omitted: emit only the element's content. (A start
                    // tag is never omitted when it has attributes, so none are lost.)
                    $parts[] = $this->toString($child);
                } else {
                    $attributes = $this->attributesToString($child);
                    $parts[] = $attributes === ''
                        ? '<' . $child->tagName
                        : '<' . $child->tagName . ' ' . $attributes;
                    $parts[] = '>' . $this->toString($child);
                }

                if (!($omitEndTags && $this->optionalTagOmission->isOptional($child))) {
                    $parts[] = '</' . $child->tagName . '>';
                }

                if (!$this->config->isDoRemoveWhitespaceAroundTags()) {
                    /** @var DOMText|null $nextSiblingTmp - false-positive error from phpstan */
                    $nextSiblingTmp = $child->nextSibling;
                    if (
                        $nextSiblingTmp instanceof DOMText
                        &&
                        $nextSiblingTmp->wholeText === ' '
                    ) {
                        if (
                            $emptyStringTmp !== 'last_was_empty'
                            &&
                            !self::partsEndWithSpace($parts)
                        ) {
                            self::rtrimParts($parts);

                            if (
                                $child->parentNode
                                &&
                                $child->parentNode->nodeName !== 'head'
                            ) {
                                $parts[] = ' ';
                            }
                        }
                        $emptyStringTmp = 'is_empty';
                    }
                }
            } elseif ($child instanceof DOMText) {
                if ($child->isElementContentWhitespace()) {
                    if (
                        $child->previousSibling !== null
                        &&
                        $child->nextSibling !== null
                    ) {
                        if (
                            (
                                $child->wholeText
                                &&
                                str_contains($child->wholeText, ' ')
                            )
                            ||
                            (
                                $emptyStringTmp !== 'last_was_empty'
                                &&
                                !self::partsEndWithSpace($parts)
                            )
                        ) {
                            self::rtrimParts($parts);

                            if (
                                $child->parentNode
                                &&
                                $child->parentNode->nodeName !== 'head'
                            ) {
                                $parts[] = ' ';
                            }
                        }
                        $emptyStringTmp = 'is_empty';
                    }
                } elseif ($child->wholeText !== '') {
                    $parts[] = $child->wholeText;
                }
            } elseif ($child instanceof DOMComment) {
                $parts[] = '<!--' . $child->textContent . '-->';
            }
        }

        return implode('', $parts);
    }

    public function attributesToString(DOMNode $node): string
    {
        if ($node->attributes === null || $node->attributes->length === 0) {
            return '';
        }

        $doOptimizeAttributes = $this->config->isDoOptimizeAttributes();
        $doRemoveOmittedQuotes = $this->config->isDoRemoveOmittedQuotes();

        // Remove quotes around attribute values, when allowed (<p class="foo"> → <p class=foo>)
        $attr_str = '';
        /** @var DOMAttr $attribute */
        foreach ($node->attributes as $attribute) {
            $attrName = $attribute->name;
            $attrValue = $attribute->value;

            if ($attr_str !== '') {
                $attr_str .= ' ';
            }

            $attr_str .= $attrName;

            if ($doOptimizeAttributes && isset(self::BOOLEAN_ATTRIBUTES[$attrName])) {
                continue;
            }

            $attr_str .= '=';

            // http://www.whatwg.org/specs/web-apps/current-work/multipage/syntax.html#attributes-0
            $omit_quotes = $doRemoveOmittedQuotes
                           &&
                           $attrValue !== ''
                           &&
                           !HtmlParser::isPlaceholder($attrName)
                           &&
                           !str_contains($attrName, ' ')
                           &&
                           strpbrk($attrValue, self::UNQUOTED_ATTRIBUTE_VALUE_FORBIDDEN_CHARS) === false;

            if (
                $doOptimizeAttributes
                &&
                (
                    $attrName === 'srcset'
                    ||
                    $attrName === 'sizes'
                )
                &&
                (
                    str_contains($attrValue, "\n")
                    ||
                    str_contains($attrValue, "\r")
                    ||
                    str_contains($attrValue, "\t")
                    ||
                    str_contains($attrValue, '  ')
                )
            ) {
                $normalizedAttrValue = preg_replace(self::SPACE_PATTERN, ' ', $attrValue);
                if ($normalizedAttrValue !== null) {
                    $attrValue = $normalizedAttrValue;
                }
            }

            if ($omit_quotes) {
                $attr_str .= $attrValue;
            } else {
                $quoteTmp = str_contains($attrValue, '"') ? "'" : '"';
                $attr_str .= $quoteTmp . $attrValue . $quoteTmp;
            }
        }

        return $attr_str;
    }

    /**
     * @param string[] $parts
     */
    private static function partsEndWithSpace(array $parts): bool
    {
        $lastIdx = array_key_last($parts);

        return $lastIdx !== null && str_ends_with($parts[$lastIdx], ' ');
    }

    /**
     * Same effect as `rtrim(implode('', $parts))` without materializing the
     * full string: pops all-whitespace parts from the tail, then rtrim's the
     * first non-all-whitespace part in place.
     *
     * @param string[] $parts
     */
    private static function rtrimParts(array &$parts): void
    {
        while ($parts !== []) {
            $lastIdx = array_key_last($parts);
            $trimmed = rtrim($parts[$lastIdx]);
            if ($trimmed !== '') {
                $parts[$lastIdx] = $trimmed;

                return;
            }
            array_pop($parts);
        }
    }
}

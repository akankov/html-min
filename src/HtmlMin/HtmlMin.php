<?php

declare(strict_types=1);

namespace Akankov\HtmlMin;

use Akankov\HtmlMin\Contract\DomObserver;
use Akankov\HtmlMin\Contract\HtmlMinInterface;
use Akankov\HtmlMin\Internal\HtmlParser;
use Akankov\HtmlMin\Observer\OptimizeAttributes;
use DOMAttr;
use DOMComment;
use DOMDocument;
use DOMDocumentType;
use DOMElement;
use DOMNode;
use DOMText;
use InvalidArgumentException;
use SplObjectStorage;

use const XML_TEXT_NODE;

class HtmlMin implements HtmlMinInterface
{
    private static string $regExSpace = "/[[:space:]]{2,}|[\r\n]/u";

    /**
     * @var string[]
     */
    private static array $optional_end_tags = [
        'html',
        'head',
        'body',
    ];

    /**
     * @var string[]
     */
    private static array $selfClosingTags = [
        'area',
        'base',
        'basefont',
        'br',
        'col',
        'command',
        'embed',
        'frame',
        'hr',
        'img',
        'input',
        'isindex',
        'keygen',
        'link',
        'meta',
        'param',
        'source',
        'track',
        'wbr',
    ];

    /**
     * @var string[]
     */
    private static array $trimWhitespaceFromTags = [
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
     * @var array<string, string>
     */
    private static array $booleanAttributes = [
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

    /**
     * @var string[]
     */
    private static array $skipTagsForRemoveWhitespace = [
        'code',
        'pre',
        'script',
        'style',
        'textarea',
    ];

    /**
     * @var array<int, string>
     */
    private array $protectedChildNodes = [];

    private string $protectedChildNodesHelper = 'html-min--voku--saved-content';

    private bool $doOptimizeViaHtmlDomParser = true;

    private bool $doOptimizeAttributes = true;

    private bool $doRemoveComments = true;

    private bool $doRemoveWhitespaceAroundTags = false;

    private bool $doRemoveOmittedQuotes = true;

    private bool $doRemoveOmittedHtmlTags = true;

    private bool $doRemoveHttpPrefixFromAttributes = false;

    private bool $doRemoveHttpsPrefixFromAttributes = false;

    private bool $doKeepHttpAndHttpsPrefixOnExternalAttributes = false;

    private bool $doMakeSameDomainsLinksRelative = false;

    /**
     * @var string[]
     */
    private array $localDomains = [];

    /**
     * @var string[]
     */
    private array $domainsToRemoveHttpPrefixFromAttributes = [
        'google.com',
        'google.de',
    ];

    /**
     * @var string[]
     */
    private array $specialHtmlCommentsStaringWith = [];

    /**
     * @var string[]
     */
    private array $specialHtmlCommentsEndingWith = [];

    private bool $doSortCssClassNames = true;

    private bool $doSortHtmlAttributes = true;

    private bool $doRemoveDeprecatedScriptCharsetAttribute = true;

    private bool $doRemoveDefaultAttributes = false;

    private bool $doRemoveDeprecatedAnchorName = true;

    private bool $doRemoveDeprecatedTypeFromStylesheetLink = true;

    private bool $doRemoveDeprecatedTypeFromStyleAndLinkTag = true;

    private bool $doRemoveDefaultMediaTypeFromStyleAndLinkTag = true;

    private bool $doRemoveDefaultTypeFromButton = false;

    private bool $doRemoveDeprecatedTypeFromScriptTag = true;

    private bool $doRemoveValueFromEmptyInput = true;

    private bool $doRemoveEmptyAttributes = true;

    private bool $doSumUpWhitespace = true;

    private bool $doRemoveSpacesBetweenTags = false;

    private bool $keepBrokenHtml = false;

    private bool $withDocType = false;

    /** @var SplObjectStorage<DomObserver, DomObserver> */
    private SplObjectStorage $domLoopObservers;

    private int $protected_tags_counter = 0;

    private bool $isHTML4 = false;

    private bool $isXHTML = false;

    /**
     * @var string[]|null
     */
    private ?array $templateLogicSyntaxInSpecialScriptTags = null;

    /**
     * @var string[]|null
     */
    private ?array $specialScriptTags = null;

    public function __construct()
    {
        $this->domLoopObservers = new SplObjectStorage();

        $this->attachObserverToTheDomLoop(new OptimizeAttributes());
    }

    public function attachObserverToTheDomLoop(DomObserver $observer): void
    {
        $this->domLoopObservers[$observer] = $observer;
    }


    public function doOptimizeAttributes(bool $doOptimizeAttributes = true): self
    {
        $this->doOptimizeAttributes = $doOptimizeAttributes;

        return $this;
    }


    public function doOptimizeViaHtmlDomParser(bool $doOptimizeViaHtmlDomParser = true): self
    {
        $this->doOptimizeViaHtmlDomParser = $doOptimizeViaHtmlDomParser;

        return $this;
    }


    public function doRemoveComments(bool $doRemoveComments = true): self
    {
        $this->doRemoveComments = $doRemoveComments;

        return $this;
    }


    public function doRemoveDefaultAttributes(bool $doRemoveDefaultAttributes = true): self
    {
        $this->doRemoveDefaultAttributes = $doRemoveDefaultAttributes;

        return $this;
    }


    public function doRemoveDeprecatedAnchorName(bool $doRemoveDeprecatedAnchorName = true): self
    {
        $this->doRemoveDeprecatedAnchorName = $doRemoveDeprecatedAnchorName;

        return $this;
    }


    public function doRemoveDeprecatedScriptCharsetAttribute(bool $doRemoveDeprecatedScriptCharsetAttribute = true): self
    {
        $this->doRemoveDeprecatedScriptCharsetAttribute = $doRemoveDeprecatedScriptCharsetAttribute;

        return $this;
    }


    public function doRemoveDeprecatedTypeFromScriptTag(bool $doRemoveDeprecatedTypeFromScriptTag = true): self
    {
        $this->doRemoveDeprecatedTypeFromScriptTag = $doRemoveDeprecatedTypeFromScriptTag;

        return $this;
    }


    public function doRemoveDeprecatedTypeFromStylesheetLink(bool $doRemoveDeprecatedTypeFromStylesheetLink = true): self
    {
        $this->doRemoveDeprecatedTypeFromStylesheetLink = $doRemoveDeprecatedTypeFromStylesheetLink;

        return $this;
    }


    public function doRemoveDeprecatedTypeFromStyleAndLinkTag(bool $doRemoveDeprecatedTypeFromStyleAndLinkTag = true): self
    {
        $this->doRemoveDeprecatedTypeFromStyleAndLinkTag = $doRemoveDeprecatedTypeFromStyleAndLinkTag;

        return $this;
    }


    public function doRemoveDefaultMediaTypeFromStyleAndLinkTag(bool $doRemoveDefaultMediaTypeFromStyleAndLinkTag = true): self
    {
        $this->doRemoveDefaultMediaTypeFromStyleAndLinkTag = $doRemoveDefaultMediaTypeFromStyleAndLinkTag;

        return $this;
    }


    public function doRemoveDefaultTypeFromButton(bool $doRemoveDefaultTypeFromButton = true): self
    {
        $this->doRemoveDefaultTypeFromButton = $doRemoveDefaultTypeFromButton;

        return $this;
    }


    public function doRemoveEmptyAttributes(bool $doRemoveEmptyAttributes = true): self
    {
        $this->doRemoveEmptyAttributes = $doRemoveEmptyAttributes;

        return $this;
    }


    public function doRemoveHttpPrefixFromAttributes(bool $doRemoveHttpPrefixFromAttributes = true): self
    {
        $this->doRemoveHttpPrefixFromAttributes = $doRemoveHttpPrefixFromAttributes;

        return $this;
    }


    public function doRemoveHttpsPrefixFromAttributes(bool $doRemoveHttpsPrefixFromAttributes = true): self
    {
        $this->doRemoveHttpsPrefixFromAttributes = $doRemoveHttpsPrefixFromAttributes;

        return $this;
    }


    public function doKeepHttpAndHttpsPrefixOnExternalAttributes(bool $doKeepHttpAndHttpsPrefixOnExternalAttributes = true): self
    {
        $this->doKeepHttpAndHttpsPrefixOnExternalAttributes = $doKeepHttpAndHttpsPrefixOnExternalAttributes;

        return $this;
    }

    /**
     * @param string[] $localDomains
     */
    public function doMakeSameDomainsLinksRelative(array $localDomains): self
    {
        /** @noinspection AlterInForeachInspection */
        foreach ($localDomains as &$localDomain) {
            $localDomain = rtrim((string) preg_replace('/(?:https?:)?\/\//i', '', $localDomain), '/');
        }

        $this->localDomains = $localDomains;
        $this->doMakeSameDomainsLinksRelative = \count($this->localDomains) > 0;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getLocalDomains(): array
    {
        return $this->localDomains;
    }


    public function doRemoveOmittedHtmlTags(bool $doRemoveOmittedHtmlTags = true): self
    {
        $this->doRemoveOmittedHtmlTags = $doRemoveOmittedHtmlTags;

        return $this;
    }


    public function doRemoveOmittedQuotes(bool $doRemoveOmittedQuotes = true): self
    {
        $this->doRemoveOmittedQuotes = $doRemoveOmittedQuotes;

        return $this;
    }


    public function doRemoveSpacesBetweenTags(bool $doRemoveSpacesBetweenTags = true): self
    {
        $this->doRemoveSpacesBetweenTags = $doRemoveSpacesBetweenTags;

        return $this;
    }


    public function doRemoveValueFromEmptyInput(bool $doRemoveValueFromEmptyInput = true): self
    {
        $this->doRemoveValueFromEmptyInput = $doRemoveValueFromEmptyInput;

        return $this;
    }


    public function doRemoveWhitespaceAroundTags(bool $doRemoveWhitespaceAroundTags = true): self
    {
        $this->doRemoveWhitespaceAroundTags = $doRemoveWhitespaceAroundTags;

        return $this;
    }


    public function doSortCssClassNames(bool $doSortCssClassNames = true): self
    {
        $this->doSortCssClassNames = $doSortCssClassNames;

        return $this;
    }


    public function doSortHtmlAttributes(bool $doSortHtmlAttributes = true): self
    {
        $this->doSortHtmlAttributes = $doSortHtmlAttributes;

        return $this;
    }


    public function doSumUpWhitespace(bool $doSumUpWhitespace = true): self
    {
        $this->doSumUpWhitespace = $doSumUpWhitespace;

        return $this;
    }

    private function domNodeAttributesToString(DOMNode $node): string
    {
        // Remove quotes around attribute values, when allowed (<p class="foo"> → <p class=foo>)
        $attr_str = '';
        if ($node->attributes !== null) {
            /** @var DOMAttr $attribute */
            foreach ($node->attributes as $attribute) {
                $attr_str .= $attribute->name;

                if (
                    $this->doOptimizeAttributes
                    &&
                    isset(self::$booleanAttributes[$attribute->name])
                ) {
                    $attr_str .= ' ';

                    continue;
                }

                $attr_str .= '=';

                // http://www.whatwg.org/specs/web-apps/current-work/multipage/syntax.html#attributes-0
                $omit_quotes = $this->doRemoveOmittedQuotes
                               &&
                               $attribute->value !== ''
                               &&
                               !str_starts_with($attribute->name, '____SIMPLE_HTML_DOM__VOKU')
                               &&
                               !str_contains($attribute->name, ' ')
                               &&
                               preg_match('/["\'=<>` \t\r\n\f]/', $attribute->value) === 0;

                $quoteTmp = '"';
                if (
                    !$omit_quotes
                    &&
                    str_contains($attribute->value, '"')
                ) {
                    $quoteTmp = "'";
                }

                if (
                    $this->doOptimizeAttributes
                    &&
                    (
                        $attribute->name === 'srcset'
                        ||
                        $attribute->name === 'sizes'
                    )
                ) {
                    $attr_val = preg_replace(self::$regExSpace, ' ', $attribute->value);
                } else {
                    $attr_val = $attribute->value;
                }

                $attr_str .= ($omit_quotes ? '' : $quoteTmp) . $attr_val . ($omit_quotes ? '' : $quoteTmp);
                $attr_str .= ' ';
            }
        }

        return trim($attr_str);
    }

    private function domNodeClosingTagOptional(DOMNode $node): bool
    {
        $tag_name = $node->nodeName;

        /** @var DOMNode|null $parent_node - false-positive error from phpstan */
        $parent_node = $node->parentNode;

        if ($parent_node) {
            $parent_tag_name = $parent_node->nodeName;
        } else {
            $parent_tag_name = null;
        }

        $nextSibling = $this->getNextSiblingOfTypeDOMElement($node);

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

        return \in_array($tag_name, self::$optional_end_tags, true)
               ||
               (
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
    }

    protected function domNodeToString(DOMNode $node): string
    {
        // init
        $html = '';
        $emptyStringTmp = '';

        foreach ($node->childNodes as $child) {
            if ($emptyStringTmp === 'is_empty') {
                $emptyStringTmp = 'last_was_empty';
            } else {
                $emptyStringTmp = '';
            }

            if ($child instanceof DOMElement) {
                $html .= rtrim('<' . $child->tagName . ' ' . $this->domNodeAttributesToString($child));
                $html .= '>' . $this->domNodeToString($child);

                if (
                    !(
                        $this->doRemoveOmittedHtmlTags
                        &&
                        !$this->isHTML4
                        &&
                        !$this->isXHTML
                        &&
                        $this->domNodeClosingTagOptional($child)
                    )
                ) {
                    $html .= '</' . $child->tagName . '>';
                }

                if (!$this->doRemoveWhitespaceAroundTags) {
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
                            substr($html, -1) !== ' '
                        ) {
                            $html = rtrim($html);

                            if (
                                $child->parentNode
                                &&
                                $child->parentNode->nodeName !== 'head'
                            ) {
                                $html .= ' ';
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
                                substr($html, -1) !== ' '
                            )
                        ) {
                            $html = rtrim($html);

                            if (
                                $child->parentNode
                                &&
                                $child->parentNode->nodeName !== 'head'
                            ) {
                                $html .= ' ';
                            }
                        }
                        $emptyStringTmp = 'is_empty';
                    }
                } else {
                    $html .= $child->wholeText;
                }
            } elseif ($child instanceof DOMComment) {
                $html .= '<!--' . $child->textContent . '-->';
            }
        }

        return $html;
    }

    private function getDoctype(DOMNode $node): string
    {
        // check the doc-type only if it wasn't generated by DomDocument itself
        if (!$this->withDocType) {
            return '';
        }

        foreach ($node->childNodes as $child) {
            if (
                $child instanceof DOMDocumentType
                &&
                $child->name
            ) {
                if (!$child->publicId && $child->systemId) {
                    $tmpTypeSystem = 'SYSTEM';
                    $tmpTypePublic = '';
                } else {
                    $tmpTypeSystem = '';
                    $tmpTypePublic = 'PUBLIC';
                }

                return '<!DOCTYPE ' . $child->name
                       . ($child->publicId ? ' ' . $tmpTypePublic . ' "' . $child->publicId . '"' : '')
                       . ($child->systemId ? ' ' . $tmpTypeSystem . ' "' . $child->systemId . '"' : '')
                       . '>';
            }
        }

        return '';
    }

    /**
     * @return string[]
     */
    public function getDomainsToRemoveHttpPrefixFromAttributes(): array
    {
        return $this->domainsToRemoveHttpPrefixFromAttributes;
    }

    public function isDoOptimizeAttributes(): bool
    {
        return $this->doOptimizeAttributes;
    }

    public function isDoOptimizeViaHtmlDomParser(): bool
    {
        return $this->doOptimizeViaHtmlDomParser;
    }

    public function isDoRemoveComments(): bool
    {
        return $this->doRemoveComments;
    }

    public function isDoRemoveDefaultAttributes(): bool
    {
        return $this->doRemoveDefaultAttributes;
    }

    public function isDoRemoveDeprecatedAnchorName(): bool
    {
        return $this->doRemoveDeprecatedAnchorName;
    }

    public function isDoRemoveDeprecatedScriptCharsetAttribute(): bool
    {
        return $this->doRemoveDeprecatedScriptCharsetAttribute;
    }

    public function isDoRemoveDeprecatedTypeFromScriptTag(): bool
    {
        return $this->doRemoveDeprecatedTypeFromScriptTag;
    }

    public function isDoRemoveDeprecatedTypeFromStylesheetLink(): bool
    {
        return $this->doRemoveDeprecatedTypeFromStylesheetLink;
    }

    public function isDoRemoveDeprecatedTypeFromStyleAndLinkTag(): bool
    {
        return $this->doRemoveDeprecatedTypeFromStyleAndLinkTag;
    }

    public function isDoRemoveDefaultMediaTypeFromStyleAndLinkTag(): bool
    {
        return $this->doRemoveDefaultMediaTypeFromStyleAndLinkTag;
    }

    public function isDoRemoveDefaultTypeFromButton(): bool
    {
        return $this->doRemoveDefaultTypeFromButton;
    }

    public function isDoRemoveEmptyAttributes(): bool
    {
        return $this->doRemoveEmptyAttributes;
    }

    public function isDoRemoveHttpPrefixFromAttributes(): bool
    {
        return $this->doRemoveHttpPrefixFromAttributes;
    }

    public function isDoRemoveHttpsPrefixFromAttributes(): bool
    {
        return $this->doRemoveHttpsPrefixFromAttributes;
    }

    public function isdoKeepHttpAndHttpsPrefixOnExternalAttributes(): bool
    {
        return $this->doKeepHttpAndHttpsPrefixOnExternalAttributes;
    }

    public function isDoMakeSameDomainsLinksRelative(): bool
    {
        return $this->doMakeSameDomainsLinksRelative;
    }

    public function isDoRemoveOmittedHtmlTags(): bool
    {
        return $this->doRemoveOmittedHtmlTags;
    }

    public function isDoRemoveOmittedQuotes(): bool
    {
        return $this->doRemoveOmittedQuotes;
    }

    public function isDoRemoveSpacesBetweenTags(): bool
    {
        return $this->doRemoveSpacesBetweenTags;
    }

    public function isDoRemoveValueFromEmptyInput(): bool
    {
        return $this->doRemoveValueFromEmptyInput;
    }

    public function isDoRemoveWhitespaceAroundTags(): bool
    {
        return $this->doRemoveWhitespaceAroundTags;
    }

    public function isDoSortCssClassNames(): bool
    {
        return $this->doSortCssClassNames;
    }

    public function isDoSortHtmlAttributes(): bool
    {
        return $this->doSortHtmlAttributes;
    }

    public function isDoSumUpWhitespace(): bool
    {
        return $this->doSumUpWhitespace;
    }

    public function isHTML4(): bool
    {
        return $this->isHTML4;
    }

    public function isXHTML(): bool
    {
        return $this->isXHTML;
    }

    public function minify(string $html, bool $multiDecodeNewHtmlEntity = false): string
    {
        if (!isset($html[0])) {
            return '';
        }

        $html = trim($html);
        if (!$html) {
            return '';
        }

        // reset
        $this->protectedChildNodes = [];

        // save old content
        $origHtml = $html;
        $origHtmlLength = \strlen($html);

        // -------------------------------------------------------------------------
        // Minify the HTML via DOM parser
        // -------------------------------------------------------------------------

        if ($this->doOptimizeViaHtmlDomParser) {
            $html = $this->minifyHtmlDom($html, $multiDecodeNewHtmlEntity);
        }

        // -------------------------------------------------------------------------
        // Trim whitespace from html-string. [protected html is still protected]
        // -------------------------------------------------------------------------

        // Remove extra white-space(s) between HTML attribute(s)
        if (str_contains($html, ' ')) {
            $htmlCleaned = preg_replace_callback(
                '#<([^/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(/?)>#',
                static fn ($matches) => '<' . $matches[1] . preg_replace('#([^\s=]+)(=([\'"]?)(.*?)\3)?(\s+|$)#su', ' $1$2', $matches[2]) . $matches[3] . '>',
                $html,
            );
            if ($htmlCleaned !== null) {
                $html = (string)$htmlCleaned;
            } else {
                $htmlCleaned = (string) preg_replace_callback(
                    '#<([^/\s<>!]+)(?:\s+([^<>]*)\s*|\s*)(/?)>#',
                    static fn ($matches) => '<' . $matches[1] . preg_replace('#([^\s=]+)(=([\'"]?)(.*?)\3)?(\s+|$)#su', ' $1$2', $matches[2]) . $matches[3] . '>',
                    $html,
                );
                $html = $htmlCleaned;
            }
        }

        if ($this->doRemoveSpacesBetweenTags) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if (str_contains($html, ' ')) {
                // Remove spaces that are between > and <
                $html = (string) preg_replace('#(>)\s(<)#', '>$2', $html);
            }
        }

        // -------------------------------------------------------------------------
        // Restore protected HTML-code.
        // -------------------------------------------------------------------------

        if (str_contains($html, $this->protectedChildNodesHelper)) {
            $html = (string) preg_replace_callback(
                '/<(?<element>' . $this->protectedChildNodesHelper . ')(?<attributes> [^>]*)?>(?<value>.*?)<\/' . $this->protectedChildNodesHelper . '>/',
                [$this, 'restoreProtectedHtml'],
                $html,
            );
        }

        // -------------------------------------------------------------------------
        // Restore protected HTML-entities.
        // -------------------------------------------------------------------------

        if ($this->doOptimizeViaHtmlDomParser) {
            $html = HtmlParser::putReplacedBackToPreserveHtmlEntities($html);
        }

        // ------------------------------------
        // Final clean-up
        // ------------------------------------

        $html = str_replace(
            [
                'html>' . "\n",
                "\n" . '<html',
                'html/>' . "\n",
                "\n" . '</html',
                'head>' . "\n",
                "\n" . '<head',
                'head/>' . "\n",
                "\n" . '</head',
            ],
            [
                'html>',
                '<html',
                'html/>',
                '</html',
                'head>',
                '<head',
                'head/>',
                '</head',
            ],
            $html,
        );

        // self closing tags, don't need a trailing slash ...
        $replace = [];
        $replacement = [];
        foreach (self::$selfClosingTags as $selfClosingTag) {
            $replace[] = '<' . $selfClosingTag . '/>';
            $replacement[] = '<' . $selfClosingTag . '>';
            $replace[] = '<' . $selfClosingTag . ' />';
            $replacement[] = '<' . $selfClosingTag . '>';
            $replace[] = '></' . $selfClosingTag . '>';
            $replacement[] = '>';
        }
        $html = str_replace(
            $replace,
            $replacement,
            $html,
        );

        // Strip trailing whitespace: libxml preserves the whitespace that
        // originally sat between the last child and the closing wrapper, but
        // we never emit a closing wrapper, so that whitespace surfaces as a
        // noise suffix.
        $html = rtrim($html);

        // ------------------------------------
        // check if compression worked
        // ------------------------------------

        if ($origHtmlLength < \strlen($html)) {
            $html = $origHtml;
        }

        return $html;
    }

    protected function getNextSiblingOfTypeDOMElement(DOMNode $node): ?DOMNode
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

    /**
     * Check if the current string is an conditional comment.
     *
     * INFO: since IE >= 10 conditional comment are not working anymore
     *
     * <!--[if expression]> HTML <![endif]-->
     * <![if expression]> HTML <![endif]>
     *
     * @param string $comment
     */
    private function isConditionalComment($comment): bool
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
     * Check if the current string is an special comment.
     *
     * @param string $comment
     */
    private function isSpecialComment($comment): bool
    {
        foreach ($this->specialHtmlCommentsStaringWith as $search) {
            if (str_starts_with($comment, $search)) {
                return true;
            }
        }

        foreach ($this->specialHtmlCommentsEndingWith as $search) {
            if (substr($comment, -\strlen($search)) === $search) {
                return true;
            }
        }

        return false;
    }

    private function minifyHtmlDom(string $html, bool $multiDecodeNewHtmlEntity): string
    {
        // Remove content before <!DOCTYPE.*> because otherwise the DOMDocument can not handle the input.
        if (stripos($html, '<!DOCTYPE') !== false) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if (
                preg_match('/(^.*?)<!DOCTYPE(?: [^>]*)?>/sui', $html, $matches_before_doctype)
                &&
                trim($matches_before_doctype[1])
            ) {
                $html = str_replace($matches_before_doctype[1], '', $html);
            }
        }

        $this->withDocType = (stripos($html, '<!DOCTYPE') === 0);

        // Reset adapter state and parse.
        HtmlParser::reset();

        $dom = HtmlParser::parse(
            $html,
            $this->keepBrokenHtml,
            $this->specialScriptTags,
            $this->templateLogicSyntaxInSpecialScriptTags,
        );

        $dom->formatOutput = false; // do not formats output with indentation

        $doctypeStr = $this->getDoctype($dom);

        if ($doctypeStr) {
            $this->isHTML4 = str_contains($doctypeStr, 'html4');
            $this->isXHTML = str_contains($doctypeStr, 'xhtml1');
        }

        // -------------------------------------------------------------------------
        // Protect <nocompress> HTML tags first.
        // -------------------------------------------------------------------------

        $this->protectTagHelper($dom, 'nocompress');

        // -------------------------------------------------------------------------
        // Notify the Observer before the minification.
        // -------------------------------------------------------------------------

        foreach (HtmlParser::findAll($dom, '*') as $element) {
            $this->notifyObserversAboutDomElementBeforeMinification($element);
        }

        // -------------------------------------------------------------------------
        // Protect HTML tags and conditional comments.
        // -------------------------------------------------------------------------

        $this->protectTags($dom);

        // -------------------------------------------------------------------------
        // Remove default HTML comments. [protected html is still protected]
        // -------------------------------------------------------------------------

        if ($this->doRemoveComments) {
            $this->removeComments($dom);
        }

        // -------------------------------------------------------------------------
        // Sum-Up extra whitespace from the Dom. [protected html is still protected]
        // -------------------------------------------------------------------------

        if ($this->doSumUpWhitespace) {
            $this->sumUpWhitespace($dom);
        }

        foreach (HtmlParser::findAll($dom, '*') as $element) {
            // -------------------------------------------------------------------------
            // Remove whitespace around tags. [protected html is still protected]
            // -------------------------------------------------------------------------

            if ($this->doRemoveWhitespaceAroundTags) {
                $this->removeWhitespaceAroundTags($element);
            }

            // -------------------------------------------------------------------------
            // Notify the Observer after the minification.
            // -------------------------------------------------------------------------

            $this->notifyObserversAboutDomElementAfterMinification($element);
        }

        // -------------------------------------------------------------------------
        // Convert the Dom into a string.
        // -------------------------------------------------------------------------

        return $doctypeStr . $this->domNodeToString($dom);
    }

    private function notifyObserversAboutDomElementAfterMinification(DOMElement $domElement): void
    {
        foreach ($this->domLoopObservers as $observer) {
            $observer->domElementAfterMinification($domElement, $this);
        }
    }

    private function notifyObserversAboutDomElementBeforeMinification(DOMElement $domElement): void
    {
        foreach ($this->domLoopObservers as $observer) {
            $observer->domElementBeforeMinification($domElement, $this);
        }
    }

    private function protectTagHelper(DOMDocument $dom, string $selector): void
    {
        foreach (HtmlParser::findAll($dom, $selector) as $element) {
            if ($element->parentNode === null) {
                continue;
            }

            $parentNode = $element->parentNode;
            if ($parentNode !== null && $parentNode->nodeValue !== null) {
                $this->protectedChildNodes[$this->protected_tags_counter] = $parentNode instanceof DOMElement
                    ? HtmlParser::innerHtml($parentNode)
                    : '';
                $parentNode->nodeValue = '<' . $this->protectedChildNodesHelper . ' data-' . $this->protectedChildNodesHelper . '="' . $this->protected_tags_counter . '"></' . $this->protectedChildNodesHelper . '>';
            }

            ++$this->protected_tags_counter;
        }
    }

    /**
     * Prevent changes of inline "styles" and "scripts".
     */
    private function protectTags(DOMDocument $dom): void
    {
        $this->protectTagHelper($dom, 'code');

        foreach (HtmlParser::findAll($dom, 'script, style') as $element) {
            if ($element->parentNode === null) {
                continue;
            }

            if ($element->tagName === 'script' || $element->tagName === 'style') {
                $attributes = HtmlParser::getAllAttributes($element);
                // skip external links
                if (isset($attributes['src'])) {
                    continue;
                }
            }

            // Match voku behavior: <script>/<style> content has its leading and
            // trailing whitespace stripped (fixture tests rely on this). Internal
            // whitespace is preserved.
            $inner = HtmlParser::innerHtml($element);
            if ($element->tagName === 'script' || $element->tagName === 'style') {
                $inner = trim($inner);
            }
            $this->protectedChildNodes[$this->protected_tags_counter] = $inner;
            $element->nodeValue = '<' . $this->protectedChildNodesHelper . ' data-' . $this->protectedChildNodesHelper . '="' . $this->protected_tags_counter . '"></' . $this->protectedChildNodesHelper . '>';

            ++$this->protected_tags_counter;
        }

        foreach (HtmlParser::findAll($dom, '//comment()') as $element) {
            if ($element->parentNode === null) {
                continue;
            }

            /** @var DOMComment $element */
            $text = $element->textContent;

            if (
                !$this->isConditionalComment($text)
                &&
                !$this->isSpecialComment($text)
            ) {
                continue;
            }

            $this->protectedChildNodes[$this->protected_tags_counter] = '<!--' . $text . '-->';

            $child = new DOMText('<' . $this->protectedChildNodesHelper . ' data-' . $this->protectedChildNodesHelper . '="' . $this->protected_tags_counter . '"></' . $this->protectedChildNodesHelper . '>');
            $parentNode = $element->parentNode;
            if ($parentNode !== null) {
                $parentNode->replaceChild($child, $element);
            }

            ++$this->protected_tags_counter;
        }
    }

    /**
     * Remove comments in the dom.
     */
    private function removeComments(DOMDocument $dom): void
    {
        foreach (HtmlParser::findAll($dom, '//comment()') as $comment) {
            /** @var DOMComment $comment */
            $val = $comment->nodeValue ?? '';
            if (!str_contains($val, '[')) {
                $parentNode = $comment->parentNode;
                if ($parentNode !== null) {
                    $parentNode->removeChild($comment);
                }
            }
        }

        $dom->normalizeDocument();
    }

    /**
     * Trim tags in the dom.
     */
    private function removeWhitespaceAroundTags(DOMElement $element): void
    {
        if (isset(self::$trimWhitespaceFromTags[$element->tagName])) {
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

                $nodeValueTmp = preg_replace(self::$regExSpace, ' ', (string) $candidate->nodeValue);
                if ($nodeValueTmp !== null) {
                    $candidate->nodeValue = $nodeValueTmp;
                }
            }
        }
    }

    /**
     * Callback function for preg_replace_callback use.
     *
     * @param array<int|string, string> $matches PREG matches
     */
    private function restoreProtectedHtml(array $matches): string
    {
        preg_match('/.*"(?<id>\d*)"/', $matches['attributes'], $matchesInner);

        return $this->protectedChildNodes[(int) ($matchesInner['id'] ?? 0)] ?? '';
    }

    /**
     * @param string[] $domainsToRemoveHttpPrefixFromAttributes
     */
    public function setDomainsToRemoveHttpPrefixFromAttributes($domainsToRemoveHttpPrefixFromAttributes): self
    {
        $this->domainsToRemoveHttpPrefixFromAttributes = $domainsToRemoveHttpPrefixFromAttributes;

        return $this;
    }

    /**
     * @param string[] $startingWith
     * @param string[] $endingWith
     */
    public function setSpecialHtmlComments(array $startingWith, array $endingWith = []): self
    {
        $this->specialHtmlCommentsStaringWith = $startingWith;
        $this->specialHtmlCommentsEndingWith = $endingWith;

        return $this;
    }

    /**
     * Sum-up extra whitespace from dom-nodes.
     */
    private function sumUpWhitespace(DOMDocument $dom): void
    {
        foreach (HtmlParser::findAll($dom, '//text()') as $text_node) {
            /** @var DOMText $text_node */
            $xp = $text_node->getNodePath();
            if ($xp === null) {
                continue;
            }

            $doSkip = false;
            foreach (self::$skipTagsForRemoveWhitespace as $pattern) {
                if (str_contains($xp, '/' . $pattern)) {
                    $doSkip = true;

                    break;
                }
            }
            if ($doSkip) {
                continue;
            }

            $nodeValueTmp = preg_replace(self::$regExSpace, ' ', $text_node->nodeValue ?? '');
            if ($nodeValueTmp !== null) {
                $text_node->nodeValue = $nodeValueTmp;
            }
        }

        $dom->normalizeDocument();
    }

    /**
     * WARNING: maybe bad for performance ...
     */
    public function useKeepBrokenHtml(bool $keepBrokenHtml): self
    {
        $this->keepBrokenHtml = $keepBrokenHtml;

        return $this;
    }

    /**
     * @param string[] $templateLogicSyntaxInSpecialScriptTags
     */
    public function overwriteTemplateLogicSyntaxInSpecialScriptTags(array $templateLogicSyntaxInSpecialScriptTags): self
    {
        foreach ($templateLogicSyntaxInSpecialScriptTags as $tmp) {
            if (!\is_string($tmp)) {
                throw new InvalidArgumentException('setTemplateLogicSyntaxInSpecialScriptTags only allows string[]');
            }
        }

        $this->templateLogicSyntaxInSpecialScriptTags = $templateLogicSyntaxInSpecialScriptTags;

        return $this;
    }


    /**
     * @param string[] $specialScriptTags
     */
    public function overwriteSpecialScriptTags(array $specialScriptTags): self
    {
        foreach ($specialScriptTags as $tag) {
            if (!\is_string($tag)) {
                throw new InvalidArgumentException('SpecialScriptTags only allows string[]');
            }
        }

        $this->specialScriptTags = $specialScriptTags;

        return $this;
    }
}

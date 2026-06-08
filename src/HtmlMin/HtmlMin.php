<?php

declare(strict_types=1);

namespace Akankov\HtmlMin;

use Akankov\HtmlMin\Config\MinifierOptions;
use Akankov\HtmlMin\Contract\DomObserver;
use Akankov\HtmlMin\Contract\HtmlMinInterface;
use Akankov\HtmlMin\Contract\ObserverPhase;
use Akankov\HtmlMin\Internal\Doctype;
use Akankov\HtmlMin\Internal\DoctypeKind;
use Akankov\HtmlMin\Internal\DomSerializer;
use Akankov\HtmlMin\Internal\HtmlParser;
use Akankov\HtmlMin\Internal\InlineContentMinifier;
use Akankov\HtmlMin\Internal\OptionalTagOmission;
use Akankov\HtmlMin\Internal\WhitespaceNormalizer;
use Akankov\HtmlMin\Observer\OptimizeAttributes;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use InvalidArgumentException;
use Override;
use Psr\Log\LoggerInterface;
use SplObjectStorage;

class HtmlMin implements HtmlMinInterface
{
    /** Inner pass for collapseAttributeWhitespace(): collapses run of spaces between attributes. */
    private const string ATTR_WHITESPACE_PATTERN = '#([^\s=]+)(=([\'"]?)(.*?)\3)?(\s+|$)#su';

    private const string ATTR_WHITESPACE_REPLACEMENT = ' $1$2';

    /** HTML5 optional-end-tag rules; see {@see OptionalTagOmission}. */
    private readonly OptionalTagOmission $optionalTagOmission;

    /** DOM → HTML5 string serialization; see {@see DomSerializer}. */
    private readonly DomSerializer $domSerializer;

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
     * @var array<int, string>
     */
    private array $protectedChildNodes = [];

    // Load-bearing literal: this tag name is inserted into a DOMElement
    // nodeValue / DOMText and the post-serialize regex expects to find it raw
    // in the output. Shorter `htmlmin-*` forms can change how libxml serializes
    // the surrounding text-vs-markup boundary and leave the placeholder
    // entity-escaped, which silently breaks restoration.
    private string $protectedChildNodesHelper = 'html-min--protected--saved-content';

    private bool $doOptimizeViaHtmlDomParser = true;

    private bool $doOptimizeAttributes = true;

    private bool $doRemoveComments = true;

    private bool $doRemoveWhitespaceAroundTags = false;

    private bool $doRemoveOmittedQuotes = true;

    private bool $doRemoveOmittedHtmlTags = true;

    private bool $doRemoveOmittedHtmlStartTags = false;

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

    private bool $doMinifyInlineCss = false;

    private bool $doMinifyInlineJs = false;

    /** Opt-in inline `<style>`/`<script>` minification; see {@see InlineContentMinifier}. */
    private readonly InlineContentMinifier $inlineContentMinifier;

    private bool $withDocType = false;

    /** @var SplObjectStorage<DomObserver, DomObserver> */
    private SplObjectStorage $domLoopBeforeObservers;

    /** @var SplObjectStorage<DomObserver, DomObserver> */
    private SplObjectStorage $domLoopAfterObservers;

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

    private ?LoggerInterface $logger = null;

    /**
     * Receive PSR-3 records for HTML parse warnings that libxml would
     * otherwise swallow. Without an injected logger the previous silent-
     * recovery behaviour is preserved.
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function __construct(?MinifierOptions $options = null)
    {
        $this->domLoopBeforeObservers = new SplObjectStorage();
        $this->domLoopAfterObservers = new SplObjectStorage();
        $this->optionalTagOmission = new OptionalTagOmission();
        $this->domSerializer = new DomSerializer($this, $this->optionalTagOmission);
        $this->inlineContentMinifier = new InlineContentMinifier();

        $this->attachObserverToTheDomLoop(new OptimizeAttributes(), ObserverPhase::After);

        if ($options !== null) {
            $this->applyOptions($options);
        }
    }

    /**
     * Copy a MinifierOptions snapshot onto the per-instance flags. Property
     * names map 1:1 minus the historical `do` prefix on the setters.
     */
    private function applyOptions(MinifierOptions $options): void
    {
        $this->doOptimizeViaHtmlDomParser = $options->optimizeViaHtmlDomParser;
        $this->doOptimizeAttributes = $options->optimizeAttributes;
        $this->doRemoveComments = $options->removeComments;
        $this->doRemoveWhitespaceAroundTags = $options->removeWhitespaceAroundTags;
        $this->doRemoveOmittedQuotes = $options->removeOmittedQuotes;
        $this->doRemoveOmittedHtmlTags = $options->removeOmittedHtmlTags;
        $this->doRemoveOmittedHtmlStartTags = $options->removeOmittedHtmlStartTags;
        $this->doRemoveHttpPrefixFromAttributes = $options->removeHttpPrefixFromAttributes;
        $this->doRemoveHttpsPrefixFromAttributes = $options->removeHttpsPrefixFromAttributes;
        $this->doKeepHttpAndHttpsPrefixOnExternalAttributes = $options->keepHttpAndHttpsPrefixOnExternalAttributes;
        $this->doSortCssClassNames = $options->sortCssClassNames;
        $this->doSortHtmlAttributes = $options->sortHtmlAttributes;
        $this->doRemoveDeprecatedScriptCharsetAttribute = $options->removeDeprecatedScriptCharsetAttribute;
        $this->doRemoveDefaultAttributes = $options->removeDefaultAttributes;
        $this->doRemoveDeprecatedAnchorName = $options->removeDeprecatedAnchorName;
        $this->doRemoveDeprecatedTypeFromStylesheetLink = $options->removeDeprecatedTypeFromStylesheetLink;
        $this->doRemoveDeprecatedTypeFromStyleAndLinkTag = $options->removeDeprecatedTypeFromStyleAndLinkTag;
        $this->doRemoveDefaultMediaTypeFromStyleAndLinkTag = $options->removeDefaultMediaTypeFromStyleAndLinkTag;
        $this->doRemoveDefaultTypeFromButton = $options->removeDefaultTypeFromButton;
        $this->doRemoveDeprecatedTypeFromScriptTag = $options->removeDeprecatedTypeFromScriptTag;
        $this->doRemoveValueFromEmptyInput = $options->removeValueFromEmptyInput;
        $this->doRemoveEmptyAttributes = $options->removeEmptyAttributes;
        $this->doSumUpWhitespace = $options->sumUpWhitespace;
        $this->doRemoveSpacesBetweenTags = $options->removeSpacesBetweenTags;
        $this->keepBrokenHtml = $options->keepBrokenHtml;
        $this->doMinifyInlineCss = $options->minifyInlineCss;
        $this->doMinifyInlineJs = $options->minifyInlineJs;
        $this->localDomains = $options->localDomains;
        $this->specialHtmlCommentsStaringWith = $options->specialHtmlCommentsStartingWith;
        $this->specialHtmlCommentsEndingWith = $options->specialHtmlCommentsEndingWith;
        $this->specialScriptTags = $options->specialScriptTags;
        $this->templateLogicSyntaxInSpecialScriptTags = $options->templateLogicSyntaxInSpecialScriptTags;
    }

    public function attachObserverToTheDomLoop(DomObserver $observer, ObserverPhase $phase = ObserverPhase::Both): void
    {
        if ($phase === ObserverPhase::Before || $phase === ObserverPhase::Both) {
            $this->domLoopBeforeObservers[$observer] = $observer;
        }

        if ($phase === ObserverPhase::After || $phase === ObserverPhase::Both) {
            $this->domLoopAfterObservers[$observer] = $observer;
        }
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


    public function doMinifyInlineCss(bool $doMinifyInlineCss = true): self
    {
        $this->doMinifyInlineCss = $doMinifyInlineCss;

        return $this;
    }


    public function doMinifyInlineJs(bool $doMinifyInlineJs = true): self
    {
        $this->doMinifyInlineJs = $doMinifyInlineJs;

        return $this;
    }


    /**
     * Replace the bundled inline CSS minifier with a user-supplied
     * callable that takes the raw CSS source and returns a minified
     * string. Pass `null` to restore the bundled default.
     *
     * @param (callable(string): string)|null $minifier
     */
    public function setInlineCssMinifier(?callable $minifier): self
    {
        $this->inlineContentMinifier->setCssMinifier($minifier);

        return $this;
    }


    /**
     * Replace the bundled inline JS minifier with a user-supplied
     * callable (e.g. wrap `matthiasmullie/minify` or a shell-out to
     * `terser`). Pass `null` to restore the bundled default.
     *
     * @param (callable(string): string)|null $minifier
     */
    public function setInlineJsMinifier(?callable $minifier): self
    {
        $this->inlineContentMinifier->setJsMinifier($minifier);

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
    #[Override]
    public function getLocalDomains(): array
    {
        return $this->localDomains;
    }


    public function doRemoveOmittedHtmlTags(bool $doRemoveOmittedHtmlTags = true): self
    {
        $this->doRemoveOmittedHtmlTags = $doRemoveOmittedHtmlTags;

        return $this;
    }

    /**
     * Opt-in: also omit the `<html>`/`<head>`/`<body>` START tags where the
     * HTML5 spec allows. Far more aggressive than end-tag omission (see
     * {@see MinifierOptions::$removeOmittedHtmlStartTags}); off by default.
     */
    public function doRemoveOmittedHtmlStartTags(bool $doRemoveOmittedHtmlStartTags = true): self
    {
        $this->doRemoveOmittedHtmlStartTags = $doRemoveOmittedHtmlStartTags;

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

    /**
     * @deprecated Internal serializer entry; use {@see DomSerializer::toString()}.
     *             Kept (delegating) so existing subclasses do not break.
     */
    protected function domNodeToString(DOMNode $node): string
    {
        return $this->domSerializer->toString($node);
    }

    #[Override]
    public function isDoOptimizeAttributes(): bool
    {
        return $this->doOptimizeAttributes;
    }

    #[Override]
    public function isDoOptimizeViaHtmlDomParser(): bool
    {
        return $this->doOptimizeViaHtmlDomParser;
    }

    #[Override]
    public function isDoMinifyInlineCss(): bool
    {
        return $this->doMinifyInlineCss;
    }

    #[Override]
    public function isDoMinifyInlineJs(): bool
    {
        return $this->doMinifyInlineJs;
    }

    #[Override]
    public function isDoRemoveComments(): bool
    {
        return $this->doRemoveComments;
    }

    #[Override]
    public function isDoRemoveDefaultAttributes(): bool
    {
        return $this->doRemoveDefaultAttributes;
    }

    #[Override]
    public function isDoRemoveDeprecatedAnchorName(): bool
    {
        return $this->doRemoveDeprecatedAnchorName;
    }

    #[Override]
    public function isDoRemoveDeprecatedScriptCharsetAttribute(): bool
    {
        return $this->doRemoveDeprecatedScriptCharsetAttribute;
    }

    #[Override]
    public function isDoRemoveDeprecatedTypeFromScriptTag(): bool
    {
        return $this->doRemoveDeprecatedTypeFromScriptTag;
    }

    #[Override]
    public function isDoRemoveDeprecatedTypeFromStylesheetLink(): bool
    {
        return $this->doRemoveDeprecatedTypeFromStylesheetLink;
    }

    #[Override]
    public function isDoRemoveDeprecatedTypeFromStyleAndLinkTag(): bool
    {
        return $this->doRemoveDeprecatedTypeFromStyleAndLinkTag;
    }

    #[Override]
    public function isDoRemoveDefaultMediaTypeFromStyleAndLinkTag(): bool
    {
        return $this->doRemoveDefaultMediaTypeFromStyleAndLinkTag;
    }

    #[Override]
    public function isDoRemoveDefaultTypeFromButton(): bool
    {
        return $this->doRemoveDefaultTypeFromButton;
    }

    #[Override]
    public function isDoRemoveEmptyAttributes(): bool
    {
        return $this->doRemoveEmptyAttributes;
    }

    #[Override]
    public function isDoRemoveHttpPrefixFromAttributes(): bool
    {
        return $this->doRemoveHttpPrefixFromAttributes;
    }

    #[Override]
    public function isDoRemoveHttpsPrefixFromAttributes(): bool
    {
        return $this->doRemoveHttpsPrefixFromAttributes;
    }

    #[Override]
    public function isDoKeepHttpAndHttpsPrefixOnExternalAttributes(): bool
    {
        return $this->doKeepHttpAndHttpsPrefixOnExternalAttributes;
    }

    #[Override]
    public function isDoMakeSameDomainsLinksRelative(): bool
    {
        return $this->doMakeSameDomainsLinksRelative;
    }

    #[Override]
    public function isDoRemoveOmittedHtmlTags(): bool
    {
        return $this->doRemoveOmittedHtmlTags;
    }

    #[Override]
    public function isDoRemoveOmittedHtmlStartTags(): bool
    {
        return $this->doRemoveOmittedHtmlStartTags;
    }

    #[Override]
    public function isDoRemoveOmittedQuotes(): bool
    {
        return $this->doRemoveOmittedQuotes;
    }

    #[Override]
    public function isDoRemoveSpacesBetweenTags(): bool
    {
        return $this->doRemoveSpacesBetweenTags;
    }

    #[Override]
    public function isDoRemoveValueFromEmptyInput(): bool
    {
        return $this->doRemoveValueFromEmptyInput;
    }

    #[Override]
    public function isDoRemoveWhitespaceAroundTags(): bool
    {
        return $this->doRemoveWhitespaceAroundTags;
    }

    #[Override]
    public function isDoSortCssClassNames(): bool
    {
        return $this->doSortCssClassNames;
    }

    #[Override]
    public function isDoSortHtmlAttributes(): bool
    {
        return $this->doSortHtmlAttributes;
    }

    #[Override]
    public function isDoSumUpWhitespace(): bool
    {
        return $this->doSumUpWhitespace;
    }

    #[Override]
    public function isHTML4(): bool
    {
        return $this->isHTML4;
    }

    #[Override]
    public function isXHTML(): bool
    {
        return $this->isXHTML;
    }

    #[Override]
    public function minify(string $html): string
    {
        if (!isset($html[0])) {
            return '';
        }

        $html = trim($html);
        if (!$html) {
            return '';
        }

        // reset per-call state so successive minify() calls on the same instance start clean
        $this->protectedChildNodes = [];
        $this->protected_tags_counter = 0;
        $this->withDocType = false;
        $this->isHTML4 = false;
        $this->isXHTML = false;

        // save old content
        $origHtml = $html;
        $origHtmlLength = \strlen($html);

        // -------------------------------------------------------------------------
        // Minify the HTML via DOM parser
        // -------------------------------------------------------------------------

        $detectedDoctype = null;
        if ($this->doOptimizeViaHtmlDomParser) {
            ['html' => $html, 'doctype' => $detectedDoctype] = $this->minifyHtmlDom($html);
        }

        // -------------------------------------------------------------------------
        // Trim whitespace from html-string. [protected html is still protected]
        // -------------------------------------------------------------------------

        // Remove extra white-space(s) between HTML attribute(s). Try the lazy
        // outer pattern first; fall back to the greedy one only if PCRE returns
        // null (catastrophic backtracking on pathological attribute soup).
        if (str_contains($html, ' ')) {
            $htmlCleaned = preg_replace_callback(
                '#<([^/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(/?)>#',
                self::collapseAttributeWhitespace(...),
                $html,
            );
            if ($htmlCleaned === null) {
                $htmlCleaned = (string) preg_replace_callback(
                    '#<([^/\s<>!]+)(?:\s+([^<>]*)\s*|\s*)(/?)>#',
                    self::collapseAttributeWhitespace(...),
                    $html,
                );
            }
            $html = $htmlCleaned;
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
                $this->restoreProtectedHtml(...),
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

        // Normalize void-element forms emitted by the parser/serializer.
        // HTML5/HTML4 want bare <br>; XHTML wants the canonical <br />.
        $replace = [];
        $replacement = [];
        if ($detectedDoctype === DoctypeKind::Xhtml1) {
            foreach (self::$selfClosingTags as $selfClosingTag) {
                $replace[] = '<' . $selfClosingTag . '/>';
                $replacement[] = '<' . $selfClosingTag . ' />';
                $replace[] = '></' . $selfClosingTag . '>';
                $replacement[] = ' />';
            }
        } else {
            foreach (self::$selfClosingTags as $selfClosingTag) {
                $replace[] = '<' . $selfClosingTag . '/>';
                $replacement[] = '<' . $selfClosingTag . '>';
                $replace[] = '<' . $selfClosingTag . ' />';
                $replacement[] = '<' . $selfClosingTag . '>';
                $replace[] = '></' . $selfClosingTag . '>';
                $replacement[] = '>';
            }
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

    /**
     * @deprecated Internal traversal helper; use {@see OptionalTagOmission::nextSiblingElement()}.
     *             Kept (delegating) so existing subclasses do not break.
     */
    protected function getNextSiblingOfTypeDOMElement(DOMNode $node): ?DOMNode
    {
        return OptionalTagOmission::nextSiblingElement($node);
    }

    /**
     * Check if the current string is an conditional comment.
     *
     * INFO: since IE >= 10 conditional comment are not working anymore
     *
     * <!--[if expression]> HTML <![endif]-->
     * <![if expression]> HTML <![endif]>
     */
    private function isConditionalComment(string $comment): bool
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
     */
    private function isSpecialComment(string $comment): bool
    {
        foreach ($this->specialHtmlCommentsStaringWith as $search) {
            if (str_starts_with($comment, $search)) {
                return true;
            }
        }

        foreach ($this->specialHtmlCommentsEndingWith as $search) {
            if (str_ends_with($comment, $search)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{html: string, doctype: ?DoctypeKind}
     */
    private function minifyHtmlDom(string $html): array
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
            $this->logger,
        );

        $dom->formatOutput = false; // do not formats output with indentation

        // Only emit a doctype the caller actually supplied — ignore one libxml
        // may have synthesised.
        $doctypeStr = $this->withDocType ? Doctype::serialize($dom) : '';
        $detectedDoctype = DoctypeKind::fromDoctypeString($doctypeStr);
        $this->isHTML4 = $detectedDoctype === DoctypeKind::Html4;
        $this->isXHTML = $detectedDoctype === DoctypeKind::Xhtml1;

        // -------------------------------------------------------------------------
        // Protect <nocompress> HTML tags first.
        // -------------------------------------------------------------------------

        $this->protectTagHelper($dom, 'nocompress', true);

        // -------------------------------------------------------------------------
        // Notify the Observer before the minification.
        // -------------------------------------------------------------------------

        if ($this->domLoopBeforeObservers->count() > 0) {
            foreach (HtmlParser::findAll($dom, '*') as $element) {
                // findAll('*') only yields elements; the instanceof narrows the type.
                if ($element instanceof DOMElement) {
                    $this->notifyObserversAboutDomElementBeforeMinification($element);
                }
            }
        }

        // -------------------------------------------------------------------------
        // Protect HTML tags and conditional comments.
        // -------------------------------------------------------------------------

        $this->protectTags($dom);

        // -------------------------------------------------------------------------
        // Remove default HTML comments. [protected html is still protected]
        // -------------------------------------------------------------------------

        if ($this->doSumUpWhitespace) {
            WhitespaceNormalizer::sumUp($dom);
        }

        foreach (HtmlParser::findAll($dom, '*') as $element) {
            // findAll('*') only yields elements; the instanceof narrows the type.
            if ($element instanceof DOMElement) {
                // ---------------------------------------------------------------------
                // Remove whitespace around tags. [protected html is still protected]
                // ---------------------------------------------------------------------
                if ($this->doRemoveWhitespaceAroundTags) {
                    WhitespaceNormalizer::removeAroundTags($element);
                }

                // ---------------------------------------------------------------------
                // Notify the Observer after the minification.
                // ---------------------------------------------------------------------
                $this->notifyObserversAboutDomElementAfterMinification($element);
            }
        }

        // -------------------------------------------------------------------------
        // Convert the Dom into a string.
        // -------------------------------------------------------------------------

        return [
            'html'    => $doctypeStr . $this->domSerializer->toString($dom),
            'doctype' => $detectedDoctype,
        ];
    }

    private function notifyObserversAboutDomElementAfterMinification(DOMElement $domElement): void
    {
        foreach ($this->domLoopAfterObservers as $observer) {
            $observer->domElementAfterMinification($domElement, $this);
        }
    }

    private function notifyObserversAboutDomElementBeforeMinification(DOMElement $domElement): void
    {
        foreach ($this->domLoopBeforeObservers as $observer) {
            $observer->domElementBeforeMinification($domElement, $this);
        }
    }

    private function protectTagHelper(DOMDocument $dom, string $selector, bool $useElementScope = false): void
    {
        foreach (HtmlParser::findAll($dom, $selector) as $element) {
            // findAll yields elements; a parsed element always has a parent.
            if ($element instanceof DOMElement && $element->parentNode !== null) {
                $placeholder = '<' . $this->protectedChildNodesHelper . ' data-' . $this->protectedChildNodesHelper . '="' . $this->protected_tags_counter . '"></' . $this->protectedChildNodesHelper . '>';

                if ($useElementScope) {
                    // Replace only the matched element's inner content with the
                    // placeholder. The element itself stays in the DOM, so its
                    // siblings continue through normal minification.
                    $this->protectedChildNodes[$this->protected_tags_counter] = HtmlParser::innerHtml($element);
                    $element->nodeValue = $placeholder;
                } else {
                    $parentNode = $element->parentNode;
                    if ($parentNode->nodeValue !== null) {
                        $this->protectedChildNodes[$this->protected_tags_counter] = $parentNode instanceof DOMElement ? HtmlParser::innerHtml($parentNode) : '';
                        $parentNode->nodeValue = $placeholder;
                    }
                }

                ++$this->protected_tags_counter;
            }
        }
    }

    /**
     * Prevent changes of inline "styles" and "scripts".
     */
    private function protectTags(DOMDocument $dom): void
    {
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
                        $this->doMinifyInlineCss,
                        $this->doMinifyInlineJs,
                        $this->logger,
                    );
                }
                $this->protectedChildNodes[$this->protected_tags_counter] = $inner;
                $element->nodeValue = '<' . $this->protectedChildNodesHelper . ' data-' . $this->protectedChildNodesHelper . '="' . $this->protected_tags_counter . '"></' . $this->protectedChildNodesHelper . '>';

                ++$this->protected_tags_counter;
            }
        }

        foreach (HtmlParser::findAll($dom, '//comment()') as $element) {
            // findAll('//comment()') yields parented comment nodes.
            if ($element instanceof DOMComment && $element->parentNode !== null) {
                $text = $element->textContent;

                if (
                    !$this->isConditionalComment($text)
                    &&
                    !$this->isSpecialComment($text)
                ) {
                    if ($this->doRemoveComments && !str_contains($text, '[')) {
                        $parentNode = $element->parentNode;
                        $parentNode->removeChild($element);
                        $didRemoveComments = true;
                    }

                    continue;
                }

                $this->protectedChildNodes[$this->protected_tags_counter] = '<!--' . $text . '-->';

                $child = new DOMText('<' . $this->protectedChildNodesHelper . ' data-' . $this->protectedChildNodesHelper . '="' . $this->protected_tags_counter . '"></' . $this->protectedChildNodesHelper . '>');
                $parentNode = $element->parentNode;
                $parentNode->replaceChild($child, $element);

                ++$this->protected_tags_counter;
            }
        }

        if ($didRemoveComments) {
            $dom->normalizeDocument();
        }
    }

    /**
     * Callback function for preg_replace_callback use.
     *
     * @param array<int|string, string> $matches PREG matches
     */
    private function restoreProtectedHtml(array $matches): string
    {
        return preg_match('/=(?:"|)?(\d+)(?:"|)?/', str_replace("'", "\a", (string) $matches['attributes']), $matchesInner) === 1
            ? ($this->protectedChildNodes[(int) $matchesInner[1]] ?? '')
            : '';
    }

    /**
     * Collapses runs of whitespace between attributes within a single HTML tag.
     *
     * Shared by the lazy/greedy fallback pair of preg_replace_callback calls in
     * minify().
     *
     * @param array<int|string, string> $matches
     */
    private static function collapseAttributeWhitespace(array $matches): string
    {
        return '<'
            . $matches[1]
            . preg_replace(self::ATTR_WHITESPACE_PATTERN, self::ATTR_WHITESPACE_REPLACEMENT, $matches[2])
            . $matches[3]
            . '>';
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
            // @phpstan-ignore function.alreadyNarrowedType
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
            // @phpstan-ignore function.alreadyNarrowedType
            if (!\is_string($tag)) {
                throw new InvalidArgumentException('SpecialScriptTags only allows string[]');
            }
        }

        $this->specialScriptTags = $specialScriptTags;

        return $this;
    }
}

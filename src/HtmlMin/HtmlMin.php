<?php

declare(strict_types=1);

namespace Akankov\HtmlMin;

use Akankov\HtmlMin\Config\MinifierOptions;
use Akankov\HtmlMin\Contract\DomObserver;
use Akankov\HtmlMin\Contract\HtmlMinInterface;
use Akankov\HtmlMin\Contract\ObserverPhase;
use Akankov\HtmlMin\Internal\DoctypeKind;
use Akankov\HtmlMin\Internal\DomSerializer;
use Akankov\HtmlMin\Internal\HtmlParser;
use Akankov\HtmlMin\Internal\Minifier\InlineCssMinifier;
use Akankov\HtmlMin\Internal\Minifier\InlineJsMinifier;
use Akankov\HtmlMin\Internal\Minifier\InlineMinifier;
use Akankov\HtmlMin\Internal\OptionalTagOmission;
use Akankov\HtmlMin\Observer\OptimizeAttributes;
use Closure;
use DOMComment;
use DOMDocument;
use DOMDocumentType;
use DOMElement;
use DOMNode;
use DOMText;
use InvalidArgumentException;
use Override;
use Psr\Log\LoggerInterface;
use SplObjectStorage;
use Throwable;

use const XML_TEXT_NODE;

class HtmlMin implements HtmlMinInterface
{
    /** Inner pass for collapseAttributeWhitespace(): collapses run of spaces between attributes. */
    private const string ATTR_WHITESPACE_PATTERN = '#([^\s=]+)(=([\'"]?)(.*?)\3)?(\s+|$)#su';

    private const string ATTR_WHITESPACE_REPLACEMENT = ' $1$2';

    private static string $regExSpace = "/[[:space:]]{2,}|[\r\n]/u";

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
    private static array $skipTagsForRemoveWhitespace = [
        'code'     => '',
        'pre'      => '',
        'script'   => '',
        'style'    => '',
        'textarea' => '',
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

    /**
     * @var (Closure(string): string)|null
     */
    private ?Closure $inlineCssMinifier = null;

    /**
     * @var (Closure(string): string)|null
     */
    private ?Closure $inlineJsMinifier = null;

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
        $this->inlineCssMinifier = $minifier === null ? null : Closure::fromCallable($minifier);

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
        $this->inlineJsMinifier = $minifier === null ? null : Closure::fromCallable($minifier);

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

        $doctypeStr = $this->getDoctype($dom);
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
                if (!$element instanceof DOMElement) {
                    continue;
                }

                $this->notifyObserversAboutDomElementBeforeMinification($element);
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
            $this->sumUpWhitespace($dom);
        }

        foreach (HtmlParser::findAll($dom, '*') as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }

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
            if (!$element instanceof DOMElement) {
                continue;
            }

            if ($element->parentNode === null) {
                continue;
            }

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
                    $this->protectedChildNodes[$this->protected_tags_counter] = $parentNode instanceof DOMElement
                        ? HtmlParser::innerHtml($parentNode)
                        : '';
                    $parentNode->nodeValue = $placeholder;
                }
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

        $didRemoveComments = false;

        foreach (HtmlParser::findAll($dom, 'script, style') as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }

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

            // Protected <script>/<style> content keeps internal whitespace, while
            // leading and trailing padding is stripped before serialization.
            $inner = HtmlParser::innerHtml($element);
            if ($element->tagName === 'script' || $element->tagName === 'style') {
                $inner = trim($inner);
                $inner = $this->maybeMinifyInlineContent($element, $inner);
            }
            $this->protectedChildNodes[$this->protected_tags_counter] = $inner;
            $element->nodeValue = '<' . $this->protectedChildNodesHelper . ' data-' . $this->protectedChildNodesHelper . '="' . $this->protected_tags_counter . '"></' . $this->protectedChildNodesHelper . '>';

            ++$this->protected_tags_counter;
        }

        foreach (HtmlParser::findAll($dom, '//comment()') as $element) {
            if (!$element instanceof DOMComment) {
                continue;
            }

            if ($element->parentNode === null) {
                continue;
            }

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

        if ($didRemoveComments) {
            $dom->normalizeDocument();
        }
    }

    /**
     * Minify the contents of an inline <style> or <script> when the
     * matching toggle is on. JSON-LD, x-template, and other non-JS
     * `type` values pass through untouched. A buggy bundled minifier
     * is logged via PSR-3 and falls back to the original source so the
     * page is never corrupted.
     */
    private function maybeMinifyInlineContent(DOMElement $element, string $inner): string
    {
        if ($inner === '') {
            return $inner;
        }

        if ($element->tagName === 'style' && $this->doMinifyInlineCss) {
            return $this->applyInlineMinifier($inner, 'css');
        }

        if (
            $element->tagName === 'script'
            && $this->doMinifyInlineJs
            && $this->isScriptSafeForJsMinification($element)
        ) {
            return $this->applyInlineMinifier($inner, 'js');
        }

        return $inner;
    }

    /**
     * @param 'css'|'js' $kind
     */
    private function applyInlineMinifier(string $source, string $kind): string
    {
        $callable = $kind === 'css' ? $this->inlineCssMinifier : $this->inlineJsMinifier;
        if ($callable !== null) {
            return $callable($source);
        }

        $minifier = $this->resolveBundledMinifier($kind);

        try {
            return $minifier->minify($source);
        } catch (Throwable $e) {
            $this->logger?->warning(
                'Inline {kind} minifier failed; passing through original source.',
                ['kind' => $kind, 'exception' => $e],
            );

            return $source;
        }
    }

    /**
     * @param 'css'|'js' $kind
     */
    private function resolveBundledMinifier(string $kind): InlineMinifier
    {
        return $kind === 'css' ? new InlineCssMinifier() : new InlineJsMinifier();
    }

    /**
     * `type` attribute allowlist for inline JS minification. Empty or
     * absent `type`, plus the canonical JS MIME types and `module`,
     * are minify-safe. Anything else (JSON-LD, x-templates, custom
     * Vue/Handlebars MIMEs) is treated as opaque data.
     */
    private function isScriptSafeForJsMinification(DOMElement $script): bool
    {
        if (!$script->hasAttribute('type')) {
            return true;
        }

        $type = strtolower(trim($script->getAttribute('type')));

        return $type === ''
            || $type === 'text/javascript'
            || $type === 'application/javascript'
            || $type === 'module';
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
        if (preg_match('/=(?:"|)?(\d+)(?:"|)?/', str_replace("'", "\a", (string) $matches['attributes']), $matchesInner) !== 1) {
            return '';
        }

        return $this->protectedChildNodes[(int) $matchesInner[1]] ?? '';
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
     * Sum-up extra whitespace from dom-nodes.
     */
    private function sumUpWhitespace(DOMDocument $dom): void
    {
        // Walking the parent chain per text node re-traverses the same ancestors
        // thousands of times on large docs. Instead, pre-compute the set of text
        // nodes that live inside any whitespace-protected ancestor and check via
        // O(1) lookup during the main pass.
        /** @var SplObjectStorage<DOMText, null> $protected */
        $protected = new SplObjectStorage();
        $skipSelector = implode(', ', array_keys(self::$skipTagsForRemoveWhitespace));
        foreach (HtmlParser::findAll($dom, $skipSelector) as $protectedAncestor) {
            if (!$protectedAncestor instanceof DOMElement) {
                continue;
            }
            self::collectDescendantTextNodes($protectedAncestor, $protected);
        }

        foreach (HtmlParser::findAll($dom, '//text()') as $text_node) {
            if (!$text_node instanceof DOMText) {
                continue;
            }

            if ($protected->offsetExists($text_node)) {
                continue;
            }

            $nodeValueTmp = preg_replace(self::$regExSpace, ' ', $text_node->nodeValue ?? '');
            if ($nodeValueTmp !== null) {
                $text_node->nodeValue = $nodeValueTmp;
            }
        }
    }

    /**
     * @param SplObjectStorage<DOMText, null> $store
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

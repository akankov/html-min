<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Config;

/**
 * Bulk configuration for {@see \Akankov\HtmlMin\HtmlMin}. Pass to the
 * constructor to set every flag in one shot instead of chaining the
 * 24+ fluent doX() setters; existing setters still work and keep
 * matching their pre-2.2 behaviour.
 *
 * Field names drop the `do` prefix that's on the equivalent HtmlMin
 * setter so this acts as a flat data record. Defaults match the
 * pre-2.2 no-arg HtmlMin() defaults exactly so an empty
 * `new MinifierOptions()` is a true no-op against the prior behaviour.
 */
final readonly class MinifierOptions
{
    /**
     * @param string[] $localDomains
     * @param string[] $specialHtmlCommentsStartingWith
     * @param string[] $specialHtmlCommentsEndingWith
     * @param string[]|null $specialScriptTags
     * @param string[]|null $templateLogicSyntaxInSpecialScriptTags
     */
    public function __construct(
        public bool $optimizeViaHtmlDomParser = true,
        public bool $optimizeAttributes = true,
        public bool $removeComments = true,
        public bool $removeWhitespaceAroundTags = false,
        public bool $removeOmittedQuotes = true,
        public bool $removeOmittedHtmlTags = true,
        public bool $removeHttpPrefixFromAttributes = false,
        public bool $removeHttpsPrefixFromAttributes = false,
        public bool $keepHttpAndHttpsPrefixOnExternalAttributes = false,
        public bool $sortCssClassNames = true,
        public bool $sortHtmlAttributes = true,
        public bool $removeDeprecatedScriptCharsetAttribute = true,
        public bool $removeDefaultAttributes = false,
        public bool $removeDeprecatedAnchorName = true,
        public bool $removeDeprecatedTypeFromStylesheetLink = true,
        public bool $removeDeprecatedTypeFromStyleAndLinkTag = true,
        public bool $removeDefaultMediaTypeFromStyleAndLinkTag = true,
        public bool $removeDefaultTypeFromButton = false,
        public bool $removeDeprecatedTypeFromScriptTag = true,
        public bool $removeValueFromEmptyInput = true,
        public bool $removeEmptyAttributes = true,
        public bool $sumUpWhitespace = true,
        public bool $removeSpacesBetweenTags = false,
        public bool $keepBrokenHtml = false,
        public array $localDomains = [],
        public array $specialHtmlCommentsStartingWith = [],
        public array $specialHtmlCommentsEndingWith = [],
        public ?array $specialScriptTags = null,
        public ?array $templateLogicSyntaxInSpecialScriptTags = null,
    ) {
    }

    /**
     * Default options — equivalent to `new HtmlMin()` with no further
     * configuration. Exists so downstream code can write
     * `MinifierOptions::defaults()` instead of `new MinifierOptions()`
     * and so future presets read alongside it as siblings.
     */
    public static function defaults(): self
    {
        return new self();
    }
}

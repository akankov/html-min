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
class MinifierOptions
{
    /**
     * @param string[] $localDomains
     * @param string[] $specialHtmlCommentsStartingWith
     * @param string[] $specialHtmlCommentsEndingWith
     * @param string[]|null $specialScriptTags
     * @param string[]|null $templateLogicSyntaxInSpecialScriptTags
     */
    public function __construct(
        public readonly bool $optimizeViaHtmlDomParser = true,
        public readonly bool $optimizeAttributes = true,
        public readonly bool $removeComments = true,
        public readonly bool $removeWhitespaceAroundTags = false,
        public readonly bool $removeOmittedQuotes = true,
        public readonly bool $removeOmittedHtmlTags = true,
        public readonly bool $removeHttpPrefixFromAttributes = false,
        public readonly bool $removeHttpsPrefixFromAttributes = false,
        public readonly bool $keepHttpAndHttpsPrefixOnExternalAttributes = false,
        public readonly bool $sortCssClassNames = true,
        public readonly bool $sortHtmlAttributes = true,
        public readonly bool $removeDeprecatedScriptCharsetAttribute = true,
        public readonly bool $removeDefaultAttributes = false,
        public readonly bool $removeDeprecatedAnchorName = true,
        public readonly bool $removeDeprecatedTypeFromStylesheetLink = true,
        public readonly bool $removeDeprecatedTypeFromStyleAndLinkTag = true,
        public readonly bool $removeDefaultMediaTypeFromStyleAndLinkTag = true,
        public readonly bool $removeDefaultTypeFromButton = false,
        public readonly bool $removeDeprecatedTypeFromScriptTag = true,
        public readonly bool $removeValueFromEmptyInput = true,
        public readonly bool $removeEmptyAttributes = true,
        public readonly bool $sumUpWhitespace = true,
        public readonly bool $removeSpacesBetweenTags = false,
        // Trusted input only: the backing rewrite is super-linear and is skipped
        // above an internal size cap. See HtmlMin::useKeepBrokenHtml().
        public readonly bool $keepBrokenHtml = false,
        public readonly bool $minifyInlineCss = false,
        public readonly bool $minifyInlineJs = false,
        public readonly array $localDomains = [],
        public readonly array $specialHtmlCommentsStartingWith = [],
        public readonly array $specialHtmlCommentsEndingWith = [],
        public readonly ?array $specialScriptTags = null,
        public readonly ?array $templateLogicSyntaxInSpecialScriptTags = null,
        // Opt-in: also omit the <html>/<head>/<body> START tags where the spec
        // allows. Off by default — it is far more aggressive than end-tag
        // omission and can reduce an effectively-empty document to an empty
        // string. Requires removeOmittedHtmlTags' usual HTML5 (non-HTML4/XHTML)
        // conditions to apply.
        public readonly bool $removeOmittedHtmlStartTags = false,
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

    /**
     * Maximum byte savings while staying within what the HTML5 spec allows.
     *
     * On top of the defaults this trims whitespace around block tags, drops
     * whitespace-only text nodes between elements, omits the optional
     * `<html>`/`<head>`/`<body>` START tags, removes spec-default attributes
     * (`<form method=get>`, `<button type=submit>`), and minifies inline
     * CSS/JS with the bundled conservative minifiers.
     *
     * Trade-offs: whitespace between *inline* elements is rendering-
     * significant, so the two whitespace options can change visual spacing
     * in markup that relies on it; start-tag omission can reduce an
     * effectively-empty document to an empty string. URL scheme stripping
     * (`removeHttpPrefixFromAttributes`/`removeHttpsPrefixFromAttributes`)
     * stays off even here because protocol-relative URLs change meaning
     * outside an http(s) context.
     */
    public static function aggressive(): self
    {
        return new self(
            removeWhitespaceAroundTags: true,
            removeDefaultAttributes: true,
            removeDefaultTypeFromButton: true,
            removeSpacesBetweenTags: true,
            minifyInlineCss: true,
            minifyInlineJs: true,
            removeOmittedHtmlStartTags: true,
        );
    }

    /**
     * Shape-preserving minification: never reorders, rewrites, or omits
     * markup constructs — only collapses whitespace runs, strips comments,
     * and drops attributes the HTML5 spec defines as redundant (deprecated
     * `type=`/`charset=` attributes and friends).
     *
     * Compared to the defaults this keeps optional end tags and attribute
     * quotes in place, leaves attribute/class order untouched, and keeps
     * empty attributes — useful when the output is diffed against the
     * input, post-processed by tools that rely on the original shape, or
     * styled via selectors like `[data-x=""]`.
     */
    public static function conservative(): self
    {
        return new self(
            removeOmittedQuotes: false,
            removeOmittedHtmlTags: false,
            sortCssClassNames: false,
            sortHtmlAttributes: false,
            removeValueFromEmptyInput: false,
            removeEmptyAttributes: false,
        );
    }
}

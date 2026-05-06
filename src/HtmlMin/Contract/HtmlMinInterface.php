<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Contract;

interface HtmlMinInterface
{
    /**
     * @param bool $decodeUtf8Specials @deprecated since 2.1.0; the flag has been ignored
     *                                 since the libxml-based parser landed and will be
     *                                 removed in 2.2.0. Pass-through for one minor only.
     */
    public function minify(string $html, bool $decodeUtf8Specials = false): string;

    public function isDoSortCssClassNames(): bool;

    public function isDoSortHtmlAttributes(): bool;

    public function isDoRemoveDeprecatedScriptCharsetAttribute(): bool;

    public function isDoRemoveDefaultAttributes(): bool;

    public function isDoRemoveDeprecatedAnchorName(): bool;

    public function isDoRemoveDeprecatedTypeFromStylesheetLink(): bool;

    public function isDoRemoveDeprecatedTypeFromStyleAndLinkTag(): bool;

    public function isDoRemoveDefaultMediaTypeFromStyleAndLinkTag(): bool;

    public function isDoRemoveDefaultTypeFromButton(): bool;

    public function isDoRemoveDeprecatedTypeFromScriptTag(): bool;

    public function isDoRemoveValueFromEmptyInput(): bool;

    public function isDoRemoveEmptyAttributes(): bool;

    public function isDoSumUpWhitespace(): bool;

    public function isDoRemoveSpacesBetweenTags(): bool;

    public function isDoOptimizeViaHtmlDomParser(): bool;

    public function isDoOptimizeAttributes(): bool;

    public function isDoRemoveComments(): bool;

    public function isDoRemoveWhitespaceAroundTags(): bool;

    public function isDoRemoveOmittedQuotes(): bool;

    public function isDoRemoveOmittedHtmlTags(): bool;

    public function isDoRemoveHttpPrefixFromAttributes(): bool;

    public function isDoRemoveHttpsPrefixFromAttributes(): bool;

    public function isDoKeepHttpAndHttpsPrefixOnExternalAttributes(): bool;

    public function isDoMakeSameDomainsLinksRelative(): bool;

    public function isHTML4(): bool;

    public function isXHTML(): bool;

    /**
     * @return string[]
     */
    public function getLocalDomains(): array;
}

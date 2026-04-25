<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Observer;

use Akankov\HtmlMin\Contract\DomObserver;
use Akankov\HtmlMin\Contract\HtmlMinInterface;
use Akankov\HtmlMin\Internal\HtmlParser;
use DOMElement;
use Override;

/**
 * Optimize HTML attributes. Protected HTML remains protected.
 *
 * Sorts HTML attributes (for better gzip) and removes redundant defaults
 * (type=text/css on link/style, type=submit on button, etc.).
 */
final class OptimizeAttributes implements DomObserver
{
    private const string REMOVABLE_EMPTY_ATTRIBUTES_PATTERN = '/^(?:class|id|style|title|lang|dir|on(?:focus|blur|change|click|dblclick|mouse(?:down|up|over|move|out)|key(?:press|down|up)))$/';

    /**
     * // https://mathiasbynens.be/demo/javascript-mime-type
     * // https://developer.mozilla.org/en/docs/Web/HTML/Element/script#attr-type
     *
     * @var string[]
     */
    private static array $executableScriptsMimeTypes = [
        'text/javascript'          => '',
        'text/ecmascript'          => '',
        'text/jscript'             => '',
        'application/javascript'   => '',
        'application/x-javascript' => '',
        'application/ecmascript'   => '',
    ];

    /**
     * Receive dom elements before the minification.
     *
     * @phan-suppress PhanUnusedPublicFinalMethodParameter
     */
    #[Override]
    public function domElementBeforeMinification(DOMElement $element, HtmlMinInterface $htmlMin): void
    {
    }

    /**
     * Receive dom elements after the minification.
     */
    #[Override]
    public function domElementAfterMinification(DOMElement $element, HtmlMinInterface $htmlMin): void
    {
        if ($element->attributes->length === 0) {
            return;
        }

        $sortHtmlAttributes = $htmlMin->isDoSortHtmlAttributes();
        $sortCssClassNames = $htmlMin->isDoSortCssClassNames();
        $makeSameDomainsLinksRelative = $htmlMin->isDoMakeSameDomainsLinksRelative();
        $removeHttpPrefix = $htmlMin->isDoRemoveHttpPrefixFromAttributes();
        $removeHttpsPrefix = $htmlMin->isDoRemoveHttpsPrefixFromAttributes();

        $attributes = HtmlParser::getAllAttributes($element);
        if ($attributes === []) {
            return;
        }

        $tagName = $element->tagName;
        $attributesAreSorted = $sortHtmlAttributes && self::isSortedAttributes($attributes);
        $hasNamespacedAttribute = $sortHtmlAttributes && self::hasNamespacedAttribute($attributes);
        $attrs = $sortHtmlAttributes ? [] : $attributes;
        $isExternal = (isset($attributes['rel']) && $attributes['rel'] === 'external')
            || (isset($attributes['target']) && $attributes['target'] === '_blank');
        $canRewriteUrlAttributes = !$isExternal;
        $localDomains = $makeSameDomainsLinksRelative ? $htmlMin->getLocalDomains() : [];
        $didChange = false;
        $didRemoveAttribute = false;

        foreach ($attributes as $attrName => $attrValue) {
            // -------------------------------------------------------------------------
            // Remove local domains from attributes.
            // -------------------------------------------------------------------------

            if ($makeSameDomainsLinksRelative && $canRewriteUrlAttributes) {
                foreach ($localDomains as $localDomain) {
                    /** @noinspection InArrayCanBeUsedInspection */
                    if (
                        (
                            $attrName === 'href'
                            ||
                            $attrName === 'src'
                            ||
                            $attrName === 'srcset'
                            ||
                            $attrName === 'action'
                        )
                        &&
                        stripos($attrValue, $localDomain) !== false
                    ) {
                        $localDomainEscaped = preg_quote($localDomain, '/');

                        $newAttrValue = (string) preg_replace("/^(?:(?:https?:)?\/\/)?{$localDomainEscaped}(?!\w)(?:\/?)/i", '/', $attrValue);
                        if ($newAttrValue !== $attrValue) {
                            $attrValue = $newAttrValue;
                            $didChange = true;
                        }
                    }
                }
            }

            // -------------------------------------------------------------------------
            // Remove optional "http:"-prefix from attributes.
            // -------------------------------------------------------------------------

            if ($removeHttpPrefix && $canRewriteUrlAttributes) {
                $previousAttrValue = $attrValue;
                $attrValue = $this->removeUrlSchemeHelper(
                    $attrValue,
                    $attrName,
                    'http',
                    $attributes,
                    $tagName,
                    $htmlMin,
                );
                if ($attrValue !== $previousAttrValue) {
                    $didChange = true;
                }
            }

            if ($removeHttpsPrefix && $canRewriteUrlAttributes) {
                $previousAttrValue = $attrValue;
                $attrValue = $this->removeUrlSchemeHelper(
                    $attrValue,
                    $attrName,
                    'https',
                    $attributes,
                    $tagName,
                    $htmlMin,
                );
                if ($attrValue !== $previousAttrValue) {
                    $didChange = true;
                }
            }

            // -------------------------------------------------------------------------
            // Remove some special attributes.
            // -------------------------------------------------------------------------

            if ($this->removeAttributeHelper(
                $tagName,
                $attrName,
                $attrValue,
                $attributes,
                $htmlMin,
            )) {
                if ($sortHtmlAttributes) {
                    $didRemoveAttribute = true;
                } else {
                    $element->removeAttribute($attrName);
                }
                $didChange = true;

                continue;
            }

            // -------------------------------------------------------------------------
            // Sort css-class-names, for better gzip results.
            // -------------------------------------------------------------------------

            if ($sortCssClassNames && $attrName === 'class' && str_contains($attrValue, ' ')) {
                $sortedAttrValue = $this->sortCssClassNames($attrName, $attrValue);
                if ($sortedAttrValue !== $attrValue) {
                    $attrValue = $sortedAttrValue;
                    $didChange = true;
                }
            }

            if ($sortHtmlAttributes) {
                $attrs[$attrName] = $attrValue;
            } elseif ($attrValue !== $attributes[$attrName]) {
                $element->setAttribute($attrName, HtmlParser::replaceToPreserveHtmlEntities($attrValue));
                $didChange = true;
            }
        }

        // -------------------------------------------------------------------------
        // Sort html-attributes, for better gzip results.
        // -------------------------------------------------------------------------

        if ($sortHtmlAttributes) {
            if (!$didChange && !$didRemoveAttribute && $attributesAreSorted && !$hasNamespacedAttribute) {
                return;
            }

            foreach (array_keys($attributes) as $attrName) {
                if ($element->hasAttribute($attrName)) {
                    $element->removeAttribute($attrName);
                }
            }

            ksort($attrs);
            foreach ($attrs as $attrName => $attrValue) {
                $attrValue = HtmlParser::replaceToPreserveHtmlEntities($attrValue);
                $element->setAttribute((string) $attrName, $attrValue);
            }
        }
    }

    /**
     * Check if the attribute can be removed.
     *
     * @param array<string, string> $allAttr
     */
    private function removeAttributeHelper(string $tag, string $attrName, string $attrValue, array $allAttr, HtmlMinInterface $htmlMin): bool
    {
        // remove defaults
        if ($htmlMin->isDoRemoveDefaultAttributes()) {
            if ($tag === 'script' && $attrName === 'language' && $attrValue === 'javascript') {
                return true;
            }

            if ($tag === 'form' && $attrName === 'method' && $attrValue === 'get') {
                return true;
            }

            if ($tag === 'form' && $attrName === 'autocomplete' && $attrValue === 'on') {
                return true;
            }

            if ($tag === 'form' && $attrName === 'enctype' && $attrValue === 'application/x-www-form-urlencoded') {
                return true;
            }

            if ($tag === 'input' && $attrName === 'type' && $attrValue === 'text') {
                return true;
            }

            if ($tag === 'textarea' && $attrName === 'wrap' && $attrValue === 'soft') {
                return true;
            }

            if ($tag === 'area' && $attrName === 'shape' && $attrValue === 'rect') {
                return true;
            }

            if ($tag === 'th' && $attrName === 'scope' && $attrValue === 'auto') {
                return true;
            }

            if ($tag === 'ol' && $attrName === 'type' && $attrValue === 'decimal') {
                return true;
            }

            if ($tag === 'ol' && $attrName === 'start' && $attrValue === '1') {
                return true;
            }

            if ($tag === 'track' && $attrName === 'kind' && $attrValue === 'subtitles') {
                return true;
            }

            if ($attrName === 'spellcheck' && $attrValue === 'default') {
                return true;
            }

            if ($attrName === 'draggable' && $attrValue === 'auto') {
                return true;
            }
        }

        // remove deprecated charset-attribute (the browser will use the charset from the HTTP-Header, anyway)
        if ($htmlMin->isDoRemoveDeprecatedScriptCharsetAttribute()) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($tag === 'script' && $attrName === 'charset' && !isset($allAttr['src'])) {
                return true;
            }
        }

        // remove deprecated anchor-jump
        if ($htmlMin->isDoRemoveDeprecatedAnchorName()) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($tag === 'a' && $attrName === 'name' && isset($allAttr['id']) && $allAttr['id'] === $attrValue) {
                return true;
            }
        }

        if ($htmlMin->isDoRemoveDefaultMediaTypeFromStyleAndLinkTag()) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if (($tag === 'link' || $tag === 'style') && $attrName === 'media' && $attrValue === 'all') {
                return true;
            }
        }

        // remove "type=text/css" for css "stylesheet"-links
        if ($htmlMin->isDoRemoveDeprecatedTypeFromStylesheetLink()) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($tag === 'link' && $attrName === 'type' && $attrValue === 'text/css' && isset($allAttr['rel']) && $allAttr['rel'] === 'stylesheet' && $htmlMin->isXHTML() === false && $htmlMin->isHTML4() === false) {
                return true;
            }
        }
        // remove deprecated css-mime-types
        if ($htmlMin->isDoRemoveDeprecatedTypeFromStyleAndLinkTag()) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if (($tag === 'link' || $tag === 'style') && $attrName === 'type' && $attrValue === 'text/css' && $htmlMin->isXHTML() === false && $htmlMin->isHTML4() === false) {
                return true;
            }
        }

        // remove deprecated script-mime-types
        if ($htmlMin->isDoRemoveDeprecatedTypeFromScriptTag()) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($tag === 'script' && $attrName === 'type' && isset(self::$executableScriptsMimeTypes[$attrValue]) && $htmlMin->isXHTML() === false && $htmlMin->isHTML4() === false) {
                return true;
            }
        }

        // remove 'type=submit' from <button type="submit">
        if ($htmlMin->isDoRemoveDefaultTypeFromButton()) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($tag === 'button' && $attrName === 'type' && $attrValue === 'submit') {
                return true;
            }
        }

        // remove 'value=""' from <input type="text">
        if ($htmlMin->isDoRemoveValueFromEmptyInput()) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($tag === 'input' && $attrName === 'value' && $attrValue === '' && isset($allAttr['type']) && $allAttr['type'] === 'text') {
                return true;
            }
        }

        // remove some empty attributes
        if ($htmlMin->isDoRemoveEmptyAttributes()) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if (trim($attrValue) === '' && preg_match(self::REMOVABLE_EMPTY_ATTRIBUTES_PATTERN, $attrName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[]         $attributes
     *
     * @noinspection PhpTooManyParametersInspection
     */
    private function removeUrlSchemeHelper(
        string $attrValue,
        string $attrName,
        string $scheme,
        array $attributes,
        string $tagName,
        HtmlMinInterface $htmlMin,
    ): string {
        /** @noinspection InArrayCanBeUsedInspection */
        if (
            !(isset($attributes['rel']) && $attributes['rel'] === 'external')
            &&
            !(isset($attributes['target']) && $attributes['target'] === '_blank')
            &&
            (
                (
                    $attrName === 'href'
                    &&
                    (
                        !$htmlMin->isdoKeepHttpAndHttpsPrefixOnExternalAttributes()
                        ||
                        $tagName === 'link'
                    )
                )
                ||
                $attrName === 'src'
                ||
                $attrName === 'srcset'
                ||
                $attrName === 'action'
            )
        ) {
            $attrValue = str_replace($scheme . '://', '//', $attrValue);
        }

        return $attrValue;
    }

    private function sortCssClassNames(int|string $attrName, string $attrValue): string
    {
        if ($attrName !== 'class' || !$attrValue) {
            return $attrValue;
        }

        $classes = array_unique(
            explode(' ', $attrValue),
        );
        sort($classes);

        $attrValue = '';
        foreach ($classes as $class) {
            if (!$class) {
                continue;
            }

            $attrValue .= trim($class) . ' ';
        }

        return trim($attrValue);
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function isSortedAttributes(array $attributes): bool
    {
        $previous = null;
        foreach (array_keys($attributes) as $attrName) {
            if ($previous !== null && strcmp($previous, $attrName) > 0) {
                return false;
            }

            $previous = $attrName;
        }

        return true;
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function hasNamespacedAttribute(array $attributes): bool
    {
        foreach (array_keys($attributes) as $attrName) {
            if (str_contains($attrName, ':')) {
                return true;
            }
        }

        return false;
    }
}

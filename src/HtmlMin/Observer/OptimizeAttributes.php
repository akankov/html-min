<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Observer;

use Akankov\HtmlMin\Contract\DomObserver;
use Akankov\HtmlMin\Contract\HtmlMinInterface;
use Akankov\HtmlMin\Internal\HtmlParser;
use DOMElement;

/**
 * Optimize HTML attributes. Protected HTML remains protected.
 *
 * Sorts HTML attributes (for better gzip) and removes redundant defaults
 * (type=text/css on link/style, type=submit on button, etc.).
 */
final class OptimizeAttributes implements DomObserver
{
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
     */
    public function domElementBeforeMinification(DOMElement $element, HtmlMinInterface $htmlMin): void
    {
    }

    /**
     * Receive dom elements after the minification.
     */
    public function domElementAfterMinification(DOMElement $element, HtmlMinInterface $htmlMin): void
    {
        $attributes = HtmlParser::getAllAttributes($element);
        if ($attributes === []) {
            return;
        }

        $tagName = $element->tagName;
        $attrs = [];
        foreach ($attributes as $attrName => $attrValue) {
            // -------------------------------------------------------------------------
            // Remove local domains from attributes.
            // -------------------------------------------------------------------------

            if ($htmlMin->isDoMakeSameDomainsLinksRelative()) {
                $localDomains = $htmlMin->getLocalDomains();
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
                        !(isset($attributes['rel']) && $attributes['rel'] === 'external')
                        &&
                        !(isset($attributes['target']) && $attributes['target'] === '_blank')
                        &&
                        stripos($attrValue, $localDomain) !== false
                    ) {
                        $localDomainEscaped = preg_quote($localDomain, '/');

                        $attrValue = (string) preg_replace("/^(?:(?:https?:)?\/\/)?{$localDomainEscaped}(?!\w)(?:\/?)/i", '/', $attrValue);
                    }
                }
            }

            // -------------------------------------------------------------------------
            // Remove optional "http:"-prefix from attributes.
            // -------------------------------------------------------------------------

            if ($htmlMin->isDoRemoveHttpPrefixFromAttributes()) {
                $attrValue = $this->removeUrlSchemeHelper(
                    $attrValue,
                    $attrName,
                    'http',
                    $attributes,
                    $tagName,
                    $htmlMin,
                );
            }

            if ($htmlMin->isDoRemoveHttpsPrefixFromAttributes()) {
                $attrValue = $this->removeUrlSchemeHelper(
                    $attrValue,
                    $attrName,
                    'https',
                    $attributes,
                    $tagName,
                    $htmlMin,
                );
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
                $element->removeAttribute($attrName);

                continue;
            }

            // -------------------------------------------------------------------------
            // Sort css-class-names, for better gzip results.
            // -------------------------------------------------------------------------

            if ($htmlMin->isDoSortCssClassNames()) {
                $attrValue = $this->sortCssClassNames($attrName, $attrValue);
            }

            if ($htmlMin->isDoSortHtmlAttributes()) {
                $attrs[$attrName] = $attrValue;
                $element->removeAttribute($attrName);
            }
        }

        // -------------------------------------------------------------------------
        // Sort html-attributes, for better gzip results.
        // -------------------------------------------------------------------------

        if ($htmlMin->isDoSortHtmlAttributes()) {
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
            if (trim($attrValue) === '' && preg_match('/^(?:class|id|style|title|lang|dir|on(?:focus|blur|change|click|dblclick|mouse(?:down|up|over|move|out)|key(?:press|down|up)))$/', $attrName)) {
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

    /**
     * @param string $attrName
     * @param string $attrValue
     */
    private function sortCssClassNames($attrName, $attrValue): string
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
}

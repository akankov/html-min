<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Internal;

use Akankov\HtmlMin\Internal\Minifier\InlineCssMinifier;
use Akankov\HtmlMin\Internal\Minifier\InlineJsMinifier;
use Akankov\HtmlMin\Internal\Minifier\InlineMinifier;
use Closure;
use DOMElement;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Coordinates opt-in minification of inline `<style>` / `<script>` contents.
 *
 * Holds the optional user-supplied minifier overrides and falls back to the
 * bundled {@see InlineCssMinifier} / {@see InlineJsMinifier}. A failing minifier
 * is logged and the original source is returned, so the page is never corrupted.
 * Extracted from HtmlMin so the inline-minification feature lives on its own.
 */
class InlineContentMinifier
{
    /** @var (Closure(string): string)|null */
    private ?Closure $cssMinifier = null;

    /** @var (Closure(string): string)|null */
    private ?Closure $jsMinifier = null;

    /**
     * @param (callable(string): string)|null $minifier
     */
    public function setCssMinifier(?callable $minifier): void
    {
        $this->cssMinifier = $minifier === null ? null : Closure::fromCallable($minifier);
    }

    /**
     * @param (callable(string): string)|null $minifier
     */
    public function setJsMinifier(?callable $minifier): void
    {
        $this->jsMinifier = $minifier === null ? null : Closure::fromCallable($minifier);
    }

    public function process(
        DOMElement $element,
        string $inner,
        bool $minifyCss,
        bool $minifyJs,
        ?LoggerInterface $logger,
    ): string {
        if ($inner === '') {
            return $inner;
        }

        if ($element->tagName === 'style' && $minifyCss) {
            return $this->apply($inner, 'css', $logger);
        }

        if (
            $element->tagName === 'script'
            && $minifyJs
            && self::isScriptSafeForJs($element)
        ) {
            return $this->apply($inner, 'js', $logger);
        }

        return $inner;
    }

    /**
     * @param 'css'|'js' $kind
     */
    private function apply(string $source, string $kind, ?LoggerInterface $logger): string
    {
        $callable = $kind === 'css' ? $this->cssMinifier : $this->jsMinifier;
        if ($callable !== null) {
            return $callable($source);
        }

        $minifier = $this->resolveBundled($kind);

        try {
            return $minifier->minify($source);
        } catch (Throwable $e) {
            $logger?->warning(
                'Inline {kind} minifier failed; passing through original source.',
                ['kind' => $kind, 'exception' => $e],
            );

            return $source;
        }
    }

    /**
     * Resolve the bundled minifier for a kind. Overridable so consumers (and
     * tests) can substitute their own bundled implementation.
     *
     * @param 'css'|'js' $kind
     */
    protected function resolveBundled(string $kind): InlineMinifier
    {
        return $kind === 'css' ? new InlineCssMinifier() : new InlineJsMinifier();
    }

    /**
     * `type` attribute allowlist for inline JS minification. Empty or absent
     * `type`, plus the canonical JS MIME types and `module`, are minify-safe.
     * Anything else (JSON-LD, x-templates, custom Vue/Handlebars MIMEs) is
     * treated as opaque data.
     */
    private static function isScriptSafeForJs(DOMElement $script): bool
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
}

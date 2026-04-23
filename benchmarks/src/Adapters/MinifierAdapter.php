<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Adapters;

interface MinifierAdapter
{
    /**
     * Short identifier used in report tables, e.g. "akankov/html-min".
     */
    public function name(): string;

    /**
     * Installed package version, e.g. "1.2.3".
     * Implementations SHOULD return "unknown" when the package metadata
     * is unavailable rather than throwing or returning an empty string —
     * the renderer treats "unknown" as a known sentinel in the header table.
     */
    public function version(): string;

    /**
     * Minify the given HTML. Must not throw on non-minifiable input;
     * if the backing library errors, the adapter catches and returns '' so
     * the caller can detect and record a failure.
     */
    public function minify(string $html): string;

    /**
     * True for adapters that skip structural HTML parsing (e.g. regex-only).
     * The report renderer marks these with a footnote and discloses the asymmetry.
     */
    public function isUnsafeReference(): bool;
}

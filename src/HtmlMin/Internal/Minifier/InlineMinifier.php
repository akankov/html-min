<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Internal\Minifier;

/**
 * Contract for minifying the contents of inline <script> or <style>
 * elements. Bundled implementations live alongside this interface;
 * users provide their own via {@see \Akankov\HtmlMin\HtmlMin::setInlineCssMinifier()}
 * or {@see \Akankov\HtmlMin\HtmlMin::setInlineJsMinifier()} as a plain
 * callable that matches this contract.
 */
interface InlineMinifier
{
    public function minify(string $source): string;
}

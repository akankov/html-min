<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Contract;

use DOMElement;

/**
 * Observer hook for dom-walk phases of the minifier.
 */
interface DomObserver
{
    /**
     * Receive dom elements before the minification.
     */
    public function domElementBeforeMinification(DOMElement $element, HtmlMinInterface $htmlMin): void;

    /**
     * Receive dom elements after the minification.
     */
    public function domElementAfterMinification(DOMElement $element, HtmlMinInterface $htmlMin): void;
}

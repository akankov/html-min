<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Contract;

/**
 * Lifecycle slot for a DomObserver attached via
 * HtmlMin::attachObserverToTheDomLoop(). The default is Both, matching
 * the pre-2.2 behaviour where a registered observer received both the
 * before- and after-minification hooks.
 */
enum ObserverPhase
{
    /** Fires only on domElementBeforeMinification(). */
    case Before;

    /** Fires only on domElementAfterMinification(). */
    case After;

    /** Fires on both hooks (default). */
    case Both;
}

<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Contract;

use Akankov\HtmlMin\Contract\DomObserver;
use Akankov\HtmlMin\Contract\HtmlMinInterface;
use Akankov\HtmlMin\Contract\ObserverPhase;
use Akankov\HtmlMin\HtmlMin;
use DOMElement;
use Override;
use PHPUnit\Framework\TestCase;

final class ObserverPhaseTest extends TestCase
{
    public function testDefaultPhaseRegistersObserverForBothHooks(): void
    {
        // Existing behaviour: a non-OptimizeAttributes observer attached
        // without an explicit phase fires for *both* before- and after-
        // minification. Keeping this default makes the new enum non-
        // breaking for callers that already use attachObserverToTheDomLoop().
        $observer = new RecordingObserver();

        $htmlMin = new HtmlMin();
        $htmlMin->attachObserverToTheDomLoop($observer);
        $htmlMin->minify('<div><span>x</span></div>');

        self::assertGreaterThan(0, $observer->beforeCalls, 'before hook must fire by default');
        self::assertGreaterThan(0, $observer->afterCalls, 'after hook must fire by default');
    }

    public function testBeforePhaseFiresOnlyBeforeHook(): void
    {
        $observer = new RecordingObserver();

        $htmlMin = new HtmlMin();
        $htmlMin->attachObserverToTheDomLoop($observer, ObserverPhase::Before);
        $htmlMin->minify('<div><span>x</span></div>');

        self::assertGreaterThan(0, $observer->beforeCalls);
        self::assertSame(0, $observer->afterCalls, 'after hook must not fire for Before-only observers');
    }

    public function testAfterPhaseFiresOnlyAfterHook(): void
    {
        $observer = new RecordingObserver();

        $htmlMin = new HtmlMin();
        $htmlMin->attachObserverToTheDomLoop($observer, ObserverPhase::After);
        $htmlMin->minify('<div><span>x</span></div>');

        self::assertSame(0, $observer->beforeCalls, 'before hook must not fire for After-only observers');
        self::assertGreaterThan(0, $observer->afterCalls);
    }
}

final class RecordingObserver implements DomObserver
{
    public int $beforeCalls = 0;

    public int $afterCalls = 0;

    #[Override]
    public function domElementBeforeMinification(DOMElement $element, HtmlMinInterface $htmlMin): void
    {
        ++$this->beforeCalls;
    }

    #[Override]
    public function domElementAfterMinification(DOMElement $element, HtmlMinInterface $htmlMin): void
    {
        ++$this->afterCalls;
    }
}

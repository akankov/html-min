<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\Contract\DomObserver;
use Akankov\HtmlMin\Contract\HtmlMinInterface;
use Akankov\HtmlMin\HtmlMin;
use DOMElement;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guards the re-entrancy contract of the shared {@see \Akankov\HtmlMin\Internal\HtmlParser}
 * static state: a minify() run masks entities into the DOM with a per-run nonce
 * and unmasks them at the end. A nested minify() (e.g. from a user inline-minifier
 * callback) would reset that state mid-run and corrupt the outer run's output, so
 * nesting must fail loudly — and a thrown exception must never leave the lock stuck
 * (which would poison every later run in a persistent worker).
 */
final class HtmlMinReentrancyTest extends TestCase
{
    public function testNestedMinifyCallThrows(): void
    {
        $inner = new HtmlMin();
        $outer = (new HtmlMin())->doMinifyInlineJs(true);
        // Misuse: recursively minifying from inside the inline-minifier callback,
        // which runs while the outer run's placeholders are still masked.
        $outer->setInlineJsMinifier(static fn (string $js): string => $inner->minify('<div>x</div>'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/nest|re-?entr/i');

        $outer->minify('<script>var a = 1;</script>');
    }

    public function testExceptionDuringMinifyReleasesReentrancyLock(): void
    {
        $throwing = new class () implements DomObserver {
            public function domElementBeforeMinification(DOMElement $element, HtmlMinInterface $htmlMin): void
            {
                throw new RuntimeException('boom');
            }

            public function domElementAfterMinification(DOMElement $element, HtmlMinInterface $htmlMin): void
            {
            }
        };

        $minifier = new HtmlMin();
        $minifier->attachObserverToTheDomLoop($throwing);

        try {
            $minifier->minify('<div>x</div>');
            self::fail('expected the observer to throw');
        } catch (RuntimeException) {
            // expected
        }

        // The finally must have released the lock; a fresh, clean run now succeeds
        // instead of falsely reporting a nested call.
        self::assertSame('<div>x</div>', (new HtmlMin())->minify('<div>x</div>'));
    }
}

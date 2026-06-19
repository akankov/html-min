<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\HtmlMin;
use Override;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * The opt-in keepBrokenHtml rewriter is super-linear (its do/while passes
 * re-scan the whole string once per balanced tag pair), so a large input can
 * burn seconds of CPU. These tests lock in the safety cap that skips the
 * rewrite for oversized input — degrading to normal parsing rather than
 * hanging — while keeping the feature intact below the cap.
 */
final class HtmlMinKeepBrokenHtmlCapTest extends TestCase
{
    public function testBelowCapPreservesBrokenHtmlAndDoesNotWarn(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var string[] */
            public array $warnings = [];

            #[Override]
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                if ($level === 'warning') {
                    $this->warnings[] = (string) $message;
                }
            }
        };

        $html = '
    </script>
    <script async src="cdnjs"></script>
    ';

        $out = (new HtmlMin())->setLogger($logger)->useKeepBrokenHtml(true)->minify($html);

        self::assertSame('</script> <script async src=cdnjs></script>', $out);
        // parse() forwards libxml warnings to the logger too, so assert specifically
        // that the cap did NOT trip for this small input.
        self::assertStringNotContainsStringIgnoringCase('keepBrokenHtml', implode("\n", $logger->warnings));
    }

    public function testOversizedInputSkipsBrokenHtmlRewriteAndWarns(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var string[] */
            public array $warnings = [];

            #[Override]
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                if ($level === 'warning') {
                    $this->warnings[] = (string) $message;
                }
            }
        };

        // Comfortably over the 128 KB cap. The quadratic rewriter would take
        // seconds on this; the cap must skip it and fall back to normal parsing.
        $html = '</script>' . str_repeat('<div class="x">  hi  </div>', 8000);
        self::assertGreaterThan(128 * 1024, \strlen($html));

        $start = microtime(true);
        $out = (new HtmlMin())->setLogger($logger)->useKeepBrokenHtml(true)->minify($html);
        $elapsed = microtime(true) - $start;

        self::assertNotSame('', $out);
        // Still minified, despite skipping broken-HTML preservation.
        self::assertStringNotContainsString('  hi  ', $out);
        // A warning names the skipped pass so the behavior is observable.
        self::assertNotSame([], $logger->warnings);
        self::assertStringContainsStringIgnoringCase('keepBrokenHtml', implode("\n", $logger->warnings));
        // Sanity: skipping the rewrite kept us far from the quadratic blow-up.
        self::assertLessThan(2.0, $elapsed);
    }
}

<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\HtmlMin;
use Override;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

final class LoggerHookTest extends TestCase
{
    public function testInjectedLoggerReceivesLibxmlParseWarnings(): void
    {
        // libxml emits warnings for HTML it has to repair: stray `<`, unknown
        // tag bodies, mismatched closers. The library used to swallow these
        // in libxml's internal buffer; with a logger attached they should
        // surface as PSR-3 records so consumers can see what got fixed up.
        $logger = new SpyLogger();

        $minifier = new HtmlMin();
        $minifier->setLogger($logger);

        // `<div<oops>` is rejected by libxml's tokeniser and reported as a
        // warning; the document still parses (libxml tolerates).
        $minifier->minify('<div<oops>content</div>');

        self::assertNotEmpty(
            $logger->records,
            'libxml warnings must reach the injected logger',
        );
    }

    public function testNoLoggerIsTheNoOpDefault(): void
    {
        // Without setLogger() the minifier must not blow up on malformed
        // input — it should keep its prior silent-recovery behaviour.
        $minifier = new HtmlMin();

        $out = $minifier->minify('<div<oops>content</div>');

        self::assertNotSame('', $out);
    }
}

final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string|Stringable, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}

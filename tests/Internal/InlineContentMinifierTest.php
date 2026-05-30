<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Internal;

use Akankov\HtmlMin\Internal\InlineContentMinifier;
use Akankov\HtmlMin\Internal\Minifier\InlineMinifier;
use DOMDocument;
use DOMElement;
use Override;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

final class InlineContentMinifierTest extends TestCase
{
    private static function element(string $tag, ?string $type = null): DOMElement
    {
        $el = (new DOMDocument())->createElement($tag);
        if ($type !== null) {
            $el->setAttribute('type', $type);
        }

        return $el;
    }

    public function testStyleContentIsCssMinifiedWhenEnabled(): void
    {
        $out = (new InlineContentMinifier())
            ->process(self::element('style'), 'a { color: red; }', true, false, null);

        self::assertSame('a{color:red}', $out);
    }

    public function testJsonLdScriptIsLeftUntouched(): void
    {
        $json = '{ "@context": "https://schema.org" }';

        $out = (new InlineContentMinifier())
            ->process(self::element('script', 'application/ld+json'), $json, false, true, null);

        self::assertSame($json, $out);
    }

    public function testCustomCssMinifierOverridesBundled(): void
    {
        $coordinator = new InlineContentMinifier();
        $coordinator->setCssMinifier(static fn (string $css): string => 'OVERRIDDEN');

        $out = $coordinator->process(self::element('style'), 'a{}', true, false, null);

        self::assertSame('OVERRIDDEN', $out);
    }

    public function testBuggyBundledMinifierIsLoggedAndFallsBackToSource(): void
    {
        // A bundled minifier that throws must not corrupt the page: the original
        // source is returned and a PSR-3 warning is emitted. Exercised via a
        // subclass that substitutes a throwing bundled minifier.
        $coordinator = new class () extends InlineContentMinifier {
            #[Override]
            protected function resolveBundled(string $kind): InlineMinifier
            {
                return new class () implements InlineMinifier {
                    #[Override]
                    public function minify(string $source): string
                    {
                        throw new RuntimeException('boom');
                    }
                };
            }
        };

        $logger = new class () extends AbstractLogger {
            /** @var list<array{level: mixed, message: string|Stringable, context: array<string, mixed>}> */
            public array $records = [];

            /**
             * @param array<string, mixed> $context
             */
            #[Override]
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };

        $out = $coordinator->process(self::element('style'), 'a{ b }', true, false, $logger);

        self::assertSame('a{ b }', $out, 'original source must be returned on failure');
        self::assertCount(1, $logger->records);
        self::assertSame('css', $logger->records[0]['context']['kind']);
    }
}

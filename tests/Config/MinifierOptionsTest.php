<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Config;

use Akankov\HtmlMin\Config\MinifierOptions;
use Akankov\HtmlMin\HtmlMin;
use Error;
use PHPUnit\Framework\TestCase;

final class MinifierOptionsTest extends TestCase
{
    public function testDefaultsMatchNoArgHtmlMin(): void
    {
        // The no-op invariant: `new HtmlMin(MinifierOptions::defaults())`
        // must produce byte-identical output to the bare `new HtmlMin()`
        // path. Anything else means a default drifted between the two
        // surfaces.
        $html = '<html><body><p>foo<!-- bar --></p></body></html>';

        $bare = (new HtmlMin())->minify($html);
        $configured = (new HtmlMin(MinifierOptions::defaults()))->minify($html);

        self::assertSame($bare, $configured);
    }

    public function testNonDefaultBoolFlowsFromOptionsToHtmlMin(): void
    {
        // doRemoveComments default is true; constructing with the flag
        // off should produce a comment-preserving output. Picks a single
        // observable knob to verify that constructor-injected options
        // actually drive behaviour (not silently ignored).
        $html = '<p>x<!-- keep me --></p>';

        $options = new MinifierOptions(removeComments: false);
        $out = (new HtmlMin($options))->minify($html);

        self::assertStringContainsString('<!-- keep me -->', $out);
    }

    public function testFluentSetterStillOverridesConstructorOptions(): void
    {
        // The fluent doX() setters must keep working even when an
        // options object is passed. They take precedence (last write
        // wins), preserving BC for code that constructs and then mutates.
        $options = new MinifierOptions(removeComments: true);

        $minifier = new HtmlMin($options);
        $minifier->doRemoveComments(false);

        $out = $minifier->minify('<p>x<!-- keep me --></p>');

        self::assertStringContainsString('<!-- keep me -->', $out);
    }

    public function testReadonlyOptionsCannotBeMutated(): void
    {
        $options = new MinifierOptions(removeComments: false);

        $this->expectException(Error::class);

        // @phpstan-ignore property.readOnlyAssignOutOfClass
        $options->removeComments = true;
    }
}

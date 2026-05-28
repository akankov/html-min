<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\Config\MinifierOptions;
use Akankov\HtmlMin\HtmlMin;
use PHPUnit\Framework\TestCase;

final class HtmlMinInlineMinificationTest extends TestCase
{
    public function testInlineCssIsLeftAloneByDefault(): void
    {
        // Back-compat: without opting in, the contents of <style> blocks
        // round-trip untouched (libxml-friendly whitespace is preserved
        // by the existing protectTags() pipeline).
        $html = '<style>a { color: red; /* keep */ }</style>';

        $out = (new HtmlMin())->minify($html);

        self::assertStringContainsString('a { color: red; /* keep */ }', $out);
    }

    public function testInlineCssToggleStripsCommentsAndWhitespace(): void
    {
        $html = '<style>a { color: red; /* danger */ }</style>';

        $out = (new HtmlMin())->doMinifyInlineCss(true)->minify($html);

        self::assertStringContainsString('<style>a{color:red}</style>', $out);
        self::assertStringNotContainsString('/* danger */', $out);
    }

    public function testInlineJsIsLeftAloneByDefault(): void
    {
        $html = '<script>// keep' . "\n" . 'let x = 1;</script>';

        $out = (new HtmlMin())->minify($html);

        self::assertStringContainsString('// keep', $out);
    }

    public function testInlineJsToggleStripsCommentsAndCollapsesSpaces(): void
    {
        $html = '<script>// keep' . "\n" . 'let   x   =   1;</script>';

        $out = (new HtmlMin())->doMinifyInlineJs(true)->minify($html);

        self::assertStringNotContainsString('// keep', $out);
        self::assertStringContainsString('let x = 1;', $out);
    }

    public function testJsonLdScriptIsPreservedEvenWhenJsToggleIsOn(): void
    {
        // application/ld+json is data, not JavaScript. Minifying it would
        // change visible content and break Google's structured-data
        // validators. The bundled JS minifier must skip it.
        $html = '<script type="application/ld+json">{ "@context": "https://schema.org" }</script>';

        $out = (new HtmlMin())->doMinifyInlineJs(true)->minify($html);

        self::assertStringContainsString('{ "@context": "https://schema.org" }', $out);
    }

    public function testTemplateScriptIsPreservedEvenWhenJsToggleIsOn(): void
    {
        // <script type="text/x-template"> holds template content (Vue,
        // Handlebars). Treat it as opaque data: internal whitespace and
        // would-be JS comments survive untouched. (Leading/trailing
        // padding is trimmed unconditionally by the existing protect-
        // tags pipeline; the guard only protects content semantics.)
        $html = '<script type="text/x-template">/* not a JS comment */<span>{{ name }}   </span></script>';

        $out = (new HtmlMin())->doMinifyInlineJs(true)->minify($html);

        self::assertStringContainsString('/* not a JS comment */', $out);
        self::assertStringContainsString('{{ name }}   ', $out);
    }

    public function testModuleScriptIsMinifiedAsJs(): void
    {
        // type="module" is JS; it must be minified when the toggle is on.
        $html = '<script type="module">// hi' . "\n" . 'import x from "y";</script>';

        $out = (new HtmlMin())->doMinifyInlineJs(true)->minify($html);

        self::assertStringNotContainsString('// hi', $out);
        self::assertStringContainsString('import x from "y";', $out);
    }

    public function testExternalScriptIsLeftAloneEvenWhenJsToggleIsOn(): void
    {
        $html = '<script src="/app.js">  some text  </script>';

        $out = (new HtmlMin())->doMinifyInlineJs(true)->minify($html);

        self::assertStringContainsString('  some text  ', $out);
    }

    public function testMinifierOptionsFieldFlowsThroughForCss(): void
    {
        $html = '<style>a { color: red; }</style>';

        $out = (new HtmlMin(new MinifierOptions(minifyInlineCss: true)))->minify($html);

        self::assertStringContainsString('a{color:red}', $out);
    }

    public function testMinifierOptionsFieldFlowsThroughForJs(): void
    {
        $html = '<script>let   x   =   1;</script>';

        $out = (new HtmlMin(new MinifierOptions(minifyInlineJs: true)))->minify($html);

        self::assertStringContainsString('let x = 1;', $out);
    }

    public function testCssToggleIsReportedByGetter(): void
    {
        $minifier = new HtmlMin();

        self::assertFalse($minifier->isDoMinifyInlineCss());

        $minifier->doMinifyInlineCss(true);

        self::assertTrue($minifier->isDoMinifyInlineCss());
    }

    public function testJsToggleIsReportedByGetter(): void
    {
        $minifier = new HtmlMin();

        self::assertFalse($minifier->isDoMinifyInlineJs());

        $minifier->doMinifyInlineJs(true);

        self::assertTrue($minifier->isDoMinifyInlineJs());
    }

    public function testCustomInlineCssMinifierCallableTakesPriorityOverBundled(): void
    {
        $html = '<style>anything</style>';

        $out = (new HtmlMin())
            ->doMinifyInlineCss(true)
            ->setInlineCssMinifier(static fn (string $css): string => 'REPLACED')
            ->minify($html);

        self::assertStringContainsString('<style>REPLACED</style>', $out);
    }

    public function testCustomInlineJsMinifierCallableTakesPriorityOverBundled(): void
    {
        $html = '<script>anything</script>';

        $out = (new HtmlMin())
            ->doMinifyInlineJs(true)
            ->setInlineJsMinifier(static fn (string $js): string => 'REPLACED')
            ->minify($html);

        self::assertStringContainsString('<script>REPLACED</script>', $out);
    }

    public function testSettingCallableToNullRestoresBundledDefault(): void
    {
        $html = '<style>a { color: red; }</style>';

        $minifier = (new HtmlMin())
            ->doMinifyInlineCss(true)
            ->setInlineCssMinifier(static fn (string $css): string => 'OVERRIDE')
            ->setInlineCssMinifier(null);

        $out = $minifier->minify($html);

        self::assertStringContainsString('a{color:red}', $out);
        self::assertStringNotContainsString('OVERRIDE', $out);
    }

    public function testFullPageSmokeWithBothTogglesOn(): void
    {
        $input = (string) file_get_contents(__DIR__ . '/fixtures/inline_minify_full_page.html');
        $expected = (string) file_get_contents(__DIR__ . '/fixtures/inline_minify_full_page_result.html');

        $minifier = (new HtmlMin())
            ->doMinifyInlineCss(true)
            ->doMinifyInlineJs(true);

        self::assertSame(trim($expected), trim($minifier->minify($input)));
    }

    public function testCustomCallableIsNotInvokedWhenToggleIsOff(): void
    {
        // Custom callable must respect the master toggle. With the toggle
        // off, the style content passes through untouched even when a
        // callable is configured — avoiding "I set the callable but
        // forgot to flip the switch" surprises.
        $html = '<style>a { color: red; }</style>';
        $invoked = false;

        $minifier = (new HtmlMin())
            ->setInlineCssMinifier(static function (string $css) use (&$invoked): string {
                $invoked = true;

                return 'NEVER';
            });

        $minifier->minify($html);

        self::assertFalse($invoked, 'Custom callable was invoked even though doMinifyInlineCss is off.');
    }
}

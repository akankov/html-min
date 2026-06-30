[![CI](https://github.com/akankov/html-min/actions/workflows/ci.yml/badge.svg)](https://github.com/akankov/html-min/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/akankov/html-min/branch/main/graph/badge.svg)](https://codecov.io/gh/akankov/html-min)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fakankov%2Fhtml-min%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/akankov/html-min/main)
[![Latest Stable Version](http://poser.pugx.org/akankov/html-min/v)](https://packagist.org/packages/akankov/html-min)
[![Monthly Downloads](http://poser.pugx.org/akankov/html-min/d/monthly)](https://packagist.org/packages/akankov/html-min)
[![Dependents](http://poser.pugx.org/akankov/html-min/dependents)](https://packagist.org/packages/akankov/html-min)
[![License](http://poser.pugx.org/akankov/html-min/license)](https://packagist.org/packages/akankov/html-min)

# html-min

A fast HTML5 compressor and minifier for PHP. Strips redundant whitespace, comments,
optional tags, and default attributes, then sorts what's left so your gzip layer has
less work to do.

Built on native `\DOMDocument` — no third-party DOM dependencies.

## Requirements

- PHP **8.3**, 8.4, or 8.5
- ext-dom, ext-libxml, ext-mbstring

## Installation

```bash
composer require akankov/html-min
```

## Usage

```php
use Akankov\HtmlMin\HtmlMin;

$html = <<<HTML
<html>
  <body>
    <ul style="">
      <li style="display: inline;" class="foo">One</li>
      <li class="foo" style="display: inline;">Two</li>
    </ul>
  </body>
</html>
HTML;

echo (new HtmlMin())->minify($html);
// <html><body><ul><li class=foo style="display: inline;">One<li class=foo style="display: inline;">Two</ul>
```

Wrap any block in `<nocompress>…</nocompress>` to keep its whitespace intact.

## Configuration

Every option is a chainable setter. All defaults are shown — the example below
reproduces the default configuration.

```php
$htmlMin = (new HtmlMin())
    // Core
    ->doOptimizeViaHtmlDomParser(true)   // run the DOM-based pass (required for most of the flags below)
    ->doRemoveComments(true)             // drop HTML comments (conditional comments are preserved)
    ->doSumUpWhitespace(true)            // collapse runs of whitespace in text nodes
    ->doRemoveWhitespaceAroundTags(false)// aggressive: also trim whitespace adjacent to block tags
    ->doRemoveSpacesBetweenTags(false)   // aggressive: remove whitespace-only text nodes between elements

    // Attribute optimization
    ->doOptimizeAttributes(true)
    ->doSortHtmlAttributes(true)         // canonical attribute order → better gzip
    ->doSortCssClassNames(true)          // canonical class order → better gzip
    ->doRemoveOmittedQuotes(true)        // class="foo" → class=foo when safe
    ->doRemoveOmittedHtmlTags(true)      // <p>x</p> → <p>x where the closing tag is optional
    ->doRemoveOmittedHtmlStartTags(false)// aggressive: also omit the <html>/<head>/<body> START tags
    ->doRemoveEmptyAttributes(true)
    ->doRemoveValueFromEmptyInput(true)
    ->doRemoveDefaultAttributes(false)   // opt-in: drop defaults like form method=get

    // URL attribute trimming
    ->doRemoveHttpPrefixFromAttributes(false)
    ->doRemoveHttpsPrefixFromAttributes(false)
    ->doKeepHttpAndHttpsPrefixOnExternalAttributes(false)
    ->doMakeSameDomainsLinksRelative([])       // e.g. ['example.com'] → strip host from same-site links

    // Deprecated attribute cleanup
    ->doRemoveDeprecatedAnchorName(true)
    ->doRemoveDeprecatedScriptCharsetAttribute(true)
    ->doRemoveDeprecatedTypeFromScriptTag(true)
    ->doRemoveDeprecatedTypeFromStylesheetLink(true)
    ->doRemoveDeprecatedTypeFromStyleAndLinkTag(true)
    ->doRemoveDefaultMediaTypeFromStyleAndLinkTag(true)
    ->doRemoveDefaultTypeFromButton(false)

    // Inline CSS / JS minification (opt-in, off by default)
    ->doMinifyInlineCss(false)           // minify the contents of inline <style> blocks
    ->doMinifyInlineJs(false);           // minify the contents of inline <script> blocks

echo $htmlMin->minify($html);
```

Each setter returns `$this`, so you can configure and call `minify()` in one chain.

### Presets

`MinifierOptions` ships three named starting points so you don't have to
reason about all 27 flags at once:

```php
use Akankov\HtmlMin\Config\MinifierOptions;
use Akankov\HtmlMin\HtmlMin;

$balanced     = new HtmlMin(MinifierOptions::defaults());     // same as new HtmlMin()
$smallest     = new HtmlMin(MinifierOptions::aggressive());
$shapeStable  = new HtmlMin(MinifierOptions::conservative());
```

- **`defaults()`** — the balanced configuration shown above. Safe for almost
  any HTML; output is spec-equivalent to the input.
- **`aggressive()`** — maximum byte savings within what the HTML5 spec
  allows. Adds block-tag whitespace trimming, whitespace-only text-node
  removal, `<html>`/`<head>`/`<body>` start-tag omission, spec-default
  attribute removal, and inline CSS/JS minification. Two trade-offs to know:
  whitespace between _inline_ elements is rendering-significant, so markup
  that relies on `</span> <span>` spacing can render tighter; and start-tag
  omission can reduce an effectively-empty document — a bare html/head/body
  skeleton — to an empty string, which is correct per spec but surprising the
  first time. URL scheme stripping stays off even here because
  protocol-relative URLs change meaning outside an http(s) context.
- **`conservative()`** — shape-preserving: only collapses whitespace runs,
  strips comments, and drops spec-redundant deprecated attributes. Keeps
  optional end tags, attribute quotes, attribute/class order, and empty
  attributes. Use it when output is diffed against input, post-processed by
  shape-sensitive tools, or styled via selectors like `[data-x=""]`.

Presets are plain constructors — start from one and override per-flag with
the fluent setters if you need a variation.

## Inline CSS and JS minification

By default the contents of `<style>` and `<script>` blocks round-trip untouched.
Enable the two opt-in toggles to minify them:

```php
$htmlMin = (new HtmlMin())
    ->doMinifyInlineCss(true)
    ->doMinifyInlineJs(true);

echo $htmlMin->minify($html);
```

The bundled minifiers are zero-dependency and conservative:

- **CSS** — strips `/* … */` comments and collapses whitespace; the contents of
  strings and `url(…)` are preserved.
- **JS** — removes comments and collapses horizontal whitespace while preserving
  newlines (so Automatic Semicolon Insertion is unaffected), strings, regex
  literals, and template literals. Identifiers are never renamed.

Scripts that are not JavaScript are left alone automatically: a `<script>`
whose `type` is, for example, `application/ld+json` or `text/x-template`, and any
`<script src="…">`, passes through unminified.

### Using a different minifier

For aggressive minification (identifier renaming, dead-code removal), plug in a
third-party tool with `setInlineCssMinifier()` / `setInlineJsMinifier()`. Each
takes any `callable(string): string`; pass `null` to restore the bundled default.

```php
use MatthiasMullie\Minify; // composer require matthiasmullie/minify

$htmlMin = (new HtmlMin())
    ->doMinifyInlineCss(true)
    ->doMinifyInlineJs(true)
    ->setInlineCssMinifier(static fn (string $css): string => (new Minify\CSS($css))->minify())
    ->setInlineJsMinifier(static fn (string $js): string => (new Minify\JS($js))->minify());

echo $htmlMin->minify($html);
```

If a bundled minifier throws, the original source is kept and a warning is sent
to the PSR-3 logger (when one is set via `setLogger()`), so a minifier bug can
never corrupt the page. User-supplied callables let their exceptions propagate.

## Command line

The package ships a small CLI at `vendor/bin/html-min` — handy for one-off
minification, build pipelines, and inspecting what a config change does:

```bash
vendor/bin/html-min page.html                      # minify a file to stdout
vendor/bin/html-min page.html --output=page.min.html
cat page.html | vendor/bin/html-min                # stdin → stdout
vendor/bin/html-min page.html --minify-inline-css --minify-inline-js
```

Exit codes: `0` success, `1` I/O failure (unreadable input / unwritable
`--output`), `2` invalid argument. `--help` prints the full usage text.
`make phar` builds a self-contained `dist/html-min.phar` for use outside a
Composer project.

## Extending

To run your own pass over every element during minification, implement
`Akankov\HtmlMin\Contract\DomObserver` and register it:

```php
use Akankov\HtmlMin\Contract\DomObserver;
use Akankov\HtmlMin\Contract\HtmlMinInterface;
use Akankov\HtmlMin\HtmlMin;

final class StripDataTestIds implements DomObserver
{
    public function domElementBeforeMinification(\DOMElement $element, HtmlMinInterface $htmlMin): void
    {
    }

    public function domElementAfterMinification(\DOMElement $element, HtmlMinInterface $htmlMin): void
    {
        if ($element->hasAttribute('data-testid')) {
            $element->removeAttribute('data-testid');
        }
    }
}

$htmlMin = new HtmlMin();
$htmlMin->attachObserverToTheDomLoop(new StripDataTestIds());
echo $htmlMin->minify($html);
```

Observer practicalities worth knowing before you ship one:

- **Phases.** `attachObserverToTheDomLoop()` takes an optional
  `ObserverPhase` (`Before`, `After`, or the default `Both`):
  `domElementBeforeMinification()` runs in the pre-pass, before attribute
  optimization; `domElementAfterMinification()` in the post-pass. Register
  for only the phase you need — each hook fires for **every element** on
  every `minify()` call, so heavy work multiplies across the document.
- **Ordering.** Observers run in registration order within a phase, and the
  built-in `OptimizeAttributes` observer is registered first (in the
  `After` phase) — your after-phase observers see already-optimized
  attributes. There is no priority system.
- **Branch on the live config.** The second argument is the owning
  `HtmlMinInterface`; its `isDo*()` getters reflect the actual flags, so an
  observer can follow e.g. `isDoRemoveHttpPrefixFromAttributes()` instead of
  duplicating configuration.
- **Exceptions propagate.** The DOM walk does not wrap observer calls — a
  throwing observer aborts the whole `minify()` call. Catch inside the
  observer if a page must never break on observer bugs.
- **Mutating the tree is the point**, but removing the element you were just
  handed (or re-parenting its ancestors) mid-walk can skip nodes — prefer
  attribute and text mutations, and removal of _descendants_.

## Benchmarks

Measured against voku/html-min, zaininnari/html-minifier, and abordage/html-min
on a corpus of real-world HTML pages.

<!-- BENCH-START -->

| adapter                   | median ms/op | geomean ms/op | parse failures | avg gzipped ratio |
| ------------------------- | ------------ | ------------- | -------------- | ----------------- |
| akankov/html-min          | 2.0          | 2.1           | 0 / 15         | 90.7%             |
| akankov/html-min (inline) | 2.2          | 2.7           | 0 / 15         | **87.6%**         |
| voku/html-min             | 3.4          | 3.8           | 0 / 15         | 90.2%             |
| zaininnari/html-minifier  | 9.5          | 8.2           | 0 / 15         | 94.8%             |
| abordage/html-min †       | **0.2**      | **0.2**       | 0 / 15         | 90.2%             |

<!-- BENCH-END -->

_The table above is regenerated by `make bench` from the latest run._
See [latest.md](latest.md) for the per-fixture detail (speed, peak memory,
gzipped compression ratio, methodology, and non-claims). Reproduce with
`make bench-install && make bench` (requires Docker).

## Development

```bash
composer install
make md-check                 # markdown formatting (Docker)
vendor/bin/phpunit            # tests
vendor/bin/phpstan analyse    # static analysis (level max)
vendor/bin/php-cs-fixer fix   # code style
```

CI runs the full matrix (PHP 8.3 / 8.4 / 8.5) on every push and pull request.

## License

MIT — see [LICENSE](LICENSE).

Originally authored by Lars Moelleken; maintained in this fork by Alex Kankov.

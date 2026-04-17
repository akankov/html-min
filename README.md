[![CI](https://github.com/akankov/html-min/actions/workflows/ci.yml/badge.svg)](https://github.com/akankov/html-min/actions/workflows/ci.yml)
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
    ->doRemoveDefaultTypeFromButton(false);

echo $htmlMin->minify($html);
```

Each setter returns `$this`, so you can configure and call `minify()` in one chain.

## Extending

To run your own pass over every element during minification, implement
`Akankov\HtmlMin\Contract\DomObserver` and register it:

```php
use Akankov\HtmlMin\Contract\DomObserver;
use Akankov\HtmlMin\Contract\HtmlMinInterface;
use Akankov\HtmlMin\HtmlMin;

final class StripDataTestIds implements DomObserver
{
    public function notifyDomNodeManipulationEvent(\DOMElement $element, HtmlMinInterface $htmlMin): void
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

## Development

```bash
composer install
vendor/bin/phpunit            # tests
vendor/bin/phpstan analyse    # static analysis (level max)
vendor/bin/php-cs-fixer fix   # code style
```

CI runs the full matrix (PHP 8.3 / 8.4 / 8.5) on every push and pull request.

## Migrating from voku/html-min

This package began as a fork of [voku/HtmlMin](https://github.com/voku/HtmlMin).
If you're upgrading from that package, see
[UPGRADE-FROM-VOKU.md](UPGRADE-FROM-VOKU.md) for the namespace map and the one
breaking change to the `DomObserver` interface.

## License

MIT — see [LICENSE](LICENSE).

Originally authored by Lars Moelleken; maintained in this fork by Alex Kankov.

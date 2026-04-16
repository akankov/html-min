# Upgrade from voku/html-min

This package is a maintained fork of [voku/HtmlMin](https://github.com/voku/HtmlMin).
Upstream went silent in May 2024 while critical PRs for PHP 8.5 deprecations
and libxml2 ≥ 2.9.14 compatibility sat unmerged. This fork lands those fixes
and modernizes the type surface.

## Package & class renames

| Before (voku)                                               | After (akankov)                                     |
| ----------------------------------------------------------- | --------------------------------------------------- |
| `voku/html-min` (Composer)                                  | `akankov/html-min` (Composer)                       |
| `voku\helper\HtmlMin`                                       | `Akankov\HtmlMin\HtmlMin`                           |
| `voku\helper\HtmlMinInterface`                              | `Akankov\HtmlMin\Contract\HtmlMinInterface`         |
| `voku\helper\HtmlMinDomObserverInterface`                   | `Akankov\HtmlMin\Contract\DomObserver`              |
| `voku\helper\HtmlMinDomObserverOptimizeAttributes`          | `Akankov\HtmlMin\Observer\OptimizeAttributes`       |

## Migration in three steps

1. Swap the Composer requirement:

   ```diff
   - "voku/html-min": "^4.5"
   + "akankov/html-min": "^1.0"
   ```

2. Update your `use` statements:

   ```diff
   - use voku\helper\HtmlMin;
   + use Akankov\HtmlMin\HtmlMin;
   ```

3. Run `composer update` and test. No public method signatures on `HtmlMin`
   have changed; fluent-API calls (`$htmlMin->doRemoveComments()->minify($html)`)
   work identically.

## Minimum PHP version

**`^8.3`** through `^8.5`. Upstream's `voku/html-min` accepted PHP 7.4; this
fork drops 7.x, 8.0, 8.1, and 8.2. PHP 7.4–8.1 are past end-of-life; 8.2 is
dropped to align the dev toolchain with PHPUnit 12 (which requires PHP 8.3+).
If you need 7.x/8.2 runtime support, pin `voku/html-min:^4.5`.

## DOM API (v1)

The `voku/simple_html_dom` dependency is gone — this fork uses native
`\DOMDocument` end-to-end. This touches exactly one public API surface:

**`DomObserver::notifyDomNodeManipulationEvent()`** now receives a
`\DOMElement` instead of `voku\helper\SimpleHtmlDomInterface`.

If you implement a custom `DomObserver`, migrate element lookups:

```diff
- public function notifyDomNodeManipulationEvent(
-     SimpleHtmlDomInterface $element,
-     HtmlMinInterface $htmlMin
- ): void {
-     if ($element->tag === 'img') { ... }
-     $attrs = $element->getAllAttributes();
- }
+ public function notifyDomNodeManipulationEvent(
+     \DOMElement $element,
+     HtmlMinInterface $htmlMin
+ ): void {
+     if ($element->tagName === 'img') { ... }
+     $attrs = \Akankov\HtmlMin\Internal\HtmlParser::getAllAttributes($element);
+ }
```

If you don't implement a custom observer, nothing changes — the built-in
`OptimizeAttributes` observer continues to run automatically.

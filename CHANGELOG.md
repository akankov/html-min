# Changelog

All notable changes to `akankov/html-min` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] — 2026-04-24

### Changed

- Performance: the minify pipeline is meaningfully faster on URL-heavy
  documents. Wikipedia-article fixture ~28% faster (144.7 → 104.1 ms,
  phpbench iter=5 rev=10), hlt ~17% faster (9.6 → 8.0 ms). URL-light
  documents are flat. No API changes; compression ratios are byte-
  identical on every fixture. See [#5].

    The biggest single win is in `HtmlParser::replaceToPreserveHtmlEntities`:
    the old code ran one full-document `str_replace` per URL found
    (O(urls × html_size)); replaced with a single `preg_replace_callback`
    pass. Secondary wins from caching the void-tag regex, consolidating
    the entity-restoration `str_ireplace` chain into one `strtr`, a
    `getElementsByTagName` fast path in `HtmlParser::findAll`, and an
    array-accumulator rewrite of the DOM serializer.

- Dist archive no longer includes `.phan` / `.php-cs-fixer` / `.phpcs.xml`
  / `rector.php` development configs — smaller Composer download.

## [1.0.0] — 2026-04-17

First stable release. Maintained fork of [voku/HtmlMin](https://github.com/voku/HtmlMin)
with a native `\DOMDocument` backend and a modernized type surface.

### Added

- `Akankov\HtmlMin\Internal\HtmlParser` — a native `\DOMDocument` + `\DOMXPath`
  adapter that replaces `voku/simple_html_dom` end-to-end.
- GitHub Actions CI matrix on PHP 8.3 / 8.4 / 8.5.
- PHPStan analysis at `level: max`.
- Phan static analysis (via `ext-ast`) in CI.
- PHP-CS-Fixer code-style enforcement.
- `UPGRADE-FROM-VOKU.md` migration guide.
- Makefile with `install`, `update`, `outdated`, `test`, `test-all`,
  `phpstan`, `phan`, `cs`, `rector`, `quality`, `ci`, `clean` targets.
- Dependabot configuration for `composer` and `github-actions` ecosystems.

### Changed

- `DomObserver::notifyDomNodeManipulationEvent()` (renamed from
  `HtmlMinDomObserverInterface`) now receives `\DOMElement` instead of
  `voku\helper\SimpleHtmlDomInterface`.
- Placeholder element names switched to hyphen-safe custom-element form
  (`htmlmin-wrapper`, `htmlmin-protected`, etc.) for libxml2 ≥ 2.9.14
  compatibility — no Reflection hacks.
- Minimum PHP version: **8.3** (upstream accepted PHP 7.4).
- Tooling: PHPUnit 12, PHPStan 2.1, Rector 2.

### Removed

- `voku/simple_html_dom` runtime dependency.
- Support for PHP < 8.3 (all EOL branches).
- Upstream's Travis/CircleCI/StyleCI configs — GitHub Actions only.

### Fixed

- PHP 8.5 deprecations (`SplObjectStorage::attach`, nullable `parentNode`).
- libxml2 ≥ 2.9.14 rejecting placeholder element names starting with `_`.

## Pre-1.0 history

This package is a fork of [voku/HtmlMin](https://github.com/voku/HtmlMin).
For the pre-fork changelog (versions 1.x – 4.5.x), see the upstream
[CHANGELOG](https://github.com/voku/HtmlMin/blob/master/CHANGELOG.md).

[#5]: https://github.com/akankov/html-min/pull/5
[1.0.0]: https://github.com/akankov/html-min/releases/tag/v1.0.0
[1.1.0]: https://github.com/akankov/html-min/releases/tag/v1.1.0
[unreleased]: https://github.com/akankov/html-min/compare/v1.1.0...HEAD

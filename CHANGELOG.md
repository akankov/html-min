# Changelog

All notable changes to `akankov/html-min` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] — 2026-04-17

First stable release. Maintained fork of [voku/HtmlMin](https://github.com/voku/HtmlMin)
with a native `\DOMDocument` backend and a modernized type surface.

### Added

- Rebranded namespace `Akankov\HtmlMin\` (old: `voku\helper\`).
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

[Unreleased]: https://github.com/akankov/html-min/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/akankov/html-min/releases/tag/v1.0.0

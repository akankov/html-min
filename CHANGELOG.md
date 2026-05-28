# Changelog

All notable changes to `akankov/html-min` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Inline CSS and JS minification (opt-in).** Two new toggles minify the
  contents of inline `<style>` and `<script>` blocks, which previously
  round-tripped untouched:
    - `HtmlMin::doMinifyInlineCss(bool $on = true)` — strips CSS comments and
      collapses whitespace. String and `url(...)` contents are preserved.
    - `HtmlMin::doMinifyInlineJs(bool $on = true)` — conservative, ASI-safe
      minification: removes comments and collapses horizontal whitespace while
      preserving newlines, strings, regex literals, and template literals.
      `<script>` elements whose `type` is not a JavaScript type (e.g.
      `application/ld+json`, `text/x-template`) pass through untouched, as do
      `<script src="...">` references.
    - Both default to **off**, so existing output is unchanged.
- **Pluggable minifier backends.** `HtmlMin::setInlineCssMinifier(?callable)`
  and `HtmlMin::setInlineJsMinifier(?callable)` replace the bundled minifiers
  with any `callable(string): string` (e.g. wrap `matthiasmullie/minify` or
  shell out to `terser`). Pass `null` to restore the bundled default. A buggy
  bundled minifier is logged via the PSR-3 logger and falls back to the
  original source so the page is never corrupted.
- `MinifierOptions` gains `minifyInlineCss` and `minifyInlineJs` fields
  (both default `false`).
- CLI flags `--minify-inline-css` and `--minify-inline-js`.

## [2.5.1] — 2026-05-13

Documentation cleanup release. Legacy upstream naming is removed from runtime
placeholders, parser/test comments, and user-facing docs. Benchmark comparisons
continue to name third-party adapters by design. Released from PR
[#16](https://github.com/akankov/html-min/pull/16).

### Changed

- Removed legacy upstream naming from runtime placeholders, parser comments,
  tests, and user-facing docs. Benchmark comparisons still retain third-party
  adapter names by design.

### Removed

- Deleted the legacy package migration guide from the published docs.

## [2.5.0] — 2026-05-07

PHAR distribution lands. `dist/html-min.phar` is a self-contained ~93 KB
binary that runs anywhere PHP 8.3+ is installed — no Composer required.
Completes the CLI distribution story started in v2.4.0. Released from PR
[#15](https://github.com/akankov/html-min/pull/15).

### Added

- `bin/build-phar.php` + `make phar` target — builds
  `dist/html-min.phar`, a self-contained ~93 KB binary bundling the
  library, runtime PSR dependencies, and Composer's autoloader.
  Invoke as `php dist/html-min.phar input.html` or directly
  (`./dist/html-min.phar`, mode `0755`). Build runs `composer install
--no-dev` against a staging copy of the project so the bundled
  autoloader doesn't try to require dev-only files-autoload entries
  (phpunit / phpstan / phan transitive deps). Files-autoload entries
  are gone, GZ compression on, stub points at the existing
  `bin/html-min` entry script.
- `dist/` added to `.gitignore`.

## [2.4.0] — 2026-05-07

CLI distribution. `vendor/bin/html-min` lands as a real binary backed by
a testable `Cli` class. PHAR distribution for non-Composer consumers
remains queued for a future release. Released from PR
[#14](https://github.com/akankov/html-min/pull/14).

### Added

- `bin/html-min` CLI binary. Reads HTML from `stdin` (or a file path
  argument) and writes minified HTML to `stdout` (or to `--output=PATH`).
  Wired into `composer.json`'s `bin` field so consumers get
  `vendor/bin/html-min` automatically. Exit codes: `0` success, `1`
  I/O failure, `2` invalid argument. Driven by a testable
  `Akankov\HtmlMin\Cli\Cli` class so the integration tests use
  `php://memory` streams and don't shell out.

## [2.3.0] — 2026-05-07

PSR-15 middleware ships, the legacy placeholder naming is gone from
the URL/entity preprocessing layer, and the prefix coupling between
`HtmlMin` and `HtmlParser` collapses behind a single predicate. No
breaking changes. Released from PRs
[#12](https://github.com/akankov/html-min/pull/12) and
[#13](https://github.com/akankov/html-min/pull/13).

### Added

- `Akankov\HtmlMin\Middleware\MinifierMiddleware` — PSR-15 middleware
  that minifies HTML response bodies on the way out of an HTTP stack.
  Inject an `HtmlMin` and a PSR-17 `StreamFactoryInterface`; optional
  third constructor argument is the content-type allowlist (default
  `['text/html']`). Other content types pass through unchanged so the
  middleware is safe to sit in front of mixed JSON / HTML stacks.
  `Content-Type` parameters (e.g. `; charset=utf-8`) are stripped
  before the allowlist comparison.

### Changed

- `composer.json` now requires `psr/http-server-middleware ^1.0`,
  `psr/http-message ^1.1 || ^2.0`, and `psr/http-factory ^1.0`. These
  are interface-only packages (~10 KB total) so the cost on consumers
  who don't use the middleware is negligible; the alternative
  (`suggest:`) trades that for "class not found" errors at instantiation
  time, which is worse UX. `nyholm/psr7` is added to `require-dev` for
  the middleware test suite.
- Internal placeholder values in `Internal\HtmlParser` no longer wear the
  old simple-html-dom-derived naming — they now use
  `____HTMLMIN_*____`. The byte shape (4-underscore delimiters, distinctive
  prefix) is preserved so the collision-resistance property is unchanged.
- The leaky hardcoded placeholder-prefix check
  check that decided whether to omit attribute-value quotes is replaced
  by a `HtmlParser::isPlaceholder()` predicate so the placeholder
  prefix has exactly one source of truth. Internal-only — no public
  surface change.

## [2.2.0] — 2026-05-06

Configuration ergonomics, observer lifecycle, PSR-3 diagnostics — plus the
deprecation cycle for the unused `minify()` parameter closes here as
scheduled. Released from PR
[#11](https://github.com/akankov/html-min/pull/11).

### Added

- `Akankov\HtmlMin\Config\MinifierOptions` — readonly value object with
  the 29 configurable knobs (24 booleans, 5 array fields). Pass to
  `new HtmlMin($options)` to bulk-configure instead of chaining the
  fluent `doX()` setters. `MinifierOptions::defaults()` returns the
  same configuration as the no-arg `new HtmlMin()` path.
- `Akankov\HtmlMin\Contract\ObserverPhase` enum with `Before`, `After`,
  and `Both` cases. `attachObserverToTheDomLoop()` now accepts a phase
  argument (default `Both`, matching pre-2.2 behaviour) so consumers
  can scope an observer to a single hook. Removes the hardcoded
  `OptimizeAttributes`-only-after exemption — the bundled observer
  registers itself with `ObserverPhase::After` in the constructor.
- `HtmlMin::setLogger(Psr\Log\LoggerInterface $logger): self` — receive
  PSR-3 records for libxml parse warnings that were previously
  swallowed in `libxml_get_errors()` and discarded. Default behaviour
  (no logger attached) is unchanged silent recovery.

### Changed

- **BREAKING.** `composer.json` now requires `psr/log: ^3.0`.
  Implementations using PSR-3 v1 or v2 will need to upgrade. The
  library only depends on the `LoggerInterface` shape, not on any
  v3-specific feature; the constraint is to avoid version drift.

### Removed

- **BREAKING.** The unused second parameter of `HtmlMin::minify()`
  (`$decodeUtf8Specials` / `$multiDecodeNewHtmlEntity`) has been
  deleted from `HtmlMinInterface` and the concrete class. Deprecated
  in 2.1.0; physically removed here as scheduled. Callers passing a
  second argument will now get an `ArgumentCountError`. No other
  output behaviour changes.

## [2.1.0] — 2026-05-06

Surgical hot-path cleanup behind the v2 contract — no behaviour change for
correct callers, measurable speed-ups on the bench corpus, and one
parameter laid down for removal in 2.2.0. Released from PR
[#10](https://github.com/akankov/html-min/pull/10).

### Added

- `Akankov\HtmlMin\Internal\DoctypeKind` enum encoding the three document
  flavours (`Html5`, `Html4`, `Xhtml1`) plus a `null` "no doctype" reading.
  Replaces an inline pair of `str_contains` checks. Fully unit-tested in
  `tests/Internal/DoctypeKindTest.php`.
- Three synthetic bench fixtures — `repeated-fragments` (1000 small
  templates), `deep-nesting` (1k-level `<div>` tree), `attribute-heavy`
  (500 nodes × 20 data-attrs). Generated on demand via
  `Corpus::synthetic()`; included in `Corpus::all()` so PhpBench picks
  them up automatically.

### Changed

- `HtmlMin::domNodeClosingTagOptional()` now short-circuits before walking
  the next-sibling chain for tags that can never have an optional closing
  tag (the common case — `div`, `span`, `a`, etc.), and memoises the
  result for the conditional set (`p`, `li`, `tr`, `td`, …) keyed by
  `(tag, parent, next-sibling-marker)`. The boolean is a pure function of
  those names so the cache survives across `minify()` calls.
- `HtmlMin::sumUpWhitespace()` pre-computes the set of text nodes inside
  whitespace-protected ancestors (`code`, `pre`, `script`, `style`,
  `textarea`) once per call, rather than walking the parent chain per
  text node. O(1) lookup replaces O(depth) per node.
- `Internal\HtmlParser::replaceToPreserveHtmlEntities()` collapses the
  AMP marker pass and the global entity-character pass into a single
  `strtr()` map, cutting one full-document scan out of every parse.
- `HtmlMin::minifyHtmlDom()` now returns
  `array{html: string, doctype: ?DoctypeKind}` instead of a bare string.
  This eliminates the `@phpstan-ignore if.alwaysFalse` that was masking
  PHPStan's inability to track property writes across the call boundary
  for the XHTML void-tag normalisation step.
- PHP 8.5 compatibility: replaced `SplObjectStorage::contains()` /
  `attach()` with `offsetExists()` / `offsetSet()` (both deprecated in
  8.5).

### Deprecated

- The second parameter on `HtmlMin::minify()` (declared as
  `$decodeUtf8Specials` on `HtmlMinInterface`, `$multiDecodeNewHtmlEntity`
  on the concrete class) has been ignored since the libxml-based parser
  replaced the old simple-html-dom backend. It is now marked `@deprecated` and
  will be removed in 2.2.0. Callers passing `true` should drop the
  argument; output is unchanged either way.

## [2.0.0] — 2026-04-29

Outcome of an audit of the library against its documented contract.
Several behaviour-correctness fixes and one removed dead public API
make this a major bump.

### Added

- New `## Summary` table at the top of the bench report aggregating
  median ms/op, geomean ms/op, parse-failure count, and average
  gzipped ratio per adapter — the per-fixture tables follow.
- README's Benchmarks section now auto-syncs the Summary table from
  `latest.md` on every `make bench`, via
  `benchmarks/bin/inject-readme-bench.php` and
  `<!-- BENCH-START -->` / `<!-- BENCH-END -->` markers.
- Bench reports annotate the git SHA with `(dirty: based on
uncommitted source)` when generated from a working tree with
  uncommitted changes.
- Failed-output cells (`parses_ok=false`) are now hidden behind
  `n/a†` in the Speed and Peak Memory tables and excluded from the
  "is best" comparison so a fast-but-broken adapter cannot claim
  fastest.
- `tests/HtmlMinTest.php` (1900 lines) split into four topical
  files: `HtmlMinTest`, `HtmlMinWhitespaceTest`,
  `HtmlMinAttributeTest`, `HtmlMinSpecialTagsTest`.
- Explicit data-provider'd test for the 13 default-attribute
  removal branches (`form method=get`, `input type=text`, …).
- Regression tests for: per-call doctype state reset, lookalike
  domain rejection, mid-string scheme stripping, narrowed
  `<nocompress>` scope, XHTML self-closing void tags, and the
  master-switch behaviour of `doOptimizeAttributes(false)`.
- New Make target `bench-test` (was missing); `make ci` now mirrors
  GitHub Actions exactly (`md-check cs-check phpstan phan
rector-check bench-phpstan bench-rector-check bench-test
test-all`).
- New CI jobs: `rector-check` (library) and
  `benchmarks-rector-check`. Composer cache extended to all eight
  CI jobs; both PHPStan jobs run with `--memory-limit=512M`.
- `tests/` is now part of phpstan and rector configuration. PHPUnit
  configs migrated to the current schema and gated with
  `failOnWarning`, `failOnNotice`, plus `failOnDeprecation`
  (library only) — vendor noise is filtered via
  `restrictNotices` / `restrictWarnings` /
  `ignoreIndirectDeprecations`.
- `benchmarks/composer.json` declares `ext-dom`, `ext-libxml`,
  `ext-mbstring`, `ext-simplexml`, `ext-zlib` (were used but
  not declared).

### Changed

- **BREAKING.** `doOptimizeAttributes(false)` is now a true
  kill-switch — the `OptimizeAttributes` observer short-circuits
  when the flag is off. Previously the observer ran regardless and
  the flag only gated two serialization-layer behaviours
  (boolean-attribute collapse and srcset/sizes whitespace).
- **BREAKING.** `<nocompress>` protection narrowed to its own
  subtree. Previously the parent element's full `innerHtml` was
  saved and replaced, which silently protected sibling nodes from
  minification.
- **BREAKING.** XHTML 1.0 inputs now emit canonical `<br />` /
  `<meta ... />` self-closing void tags. Previously every void tag
  collapsed to HTML5-style `<br>`, producing invalid XHTML output.
- **BREAKING.** `OptimizeAttributes` HTTP/HTTPS prefix stripping
  now only fires at the value start or immediately after a comma
  separator (anchoring `(^|,\s*)`). Previously the global
  `str_replace` mangled query-parameter URLs (e.g.
  `?to=http://other`) and was the wrong shape for `srcset` entries.
- **BREAKING.** `composer.json` PHP constraint tightened from
  `^8.3` to `8.3.* || 8.4.* || 8.5.*` to match the versions CI
  actually exercises. PHP 8.6+ installs that worked under the
  permissive constraint will need an explicit composer bump.
- Renamed the typo'd public method
  `isdoKeepHttpAndHttpsPrefixOnExternalAttributes()` →
  `isDoKeep…()` (capital D). PHP method dispatch is case-insensitive
  so existing external callers keep compiling, but IDEs, phpstan,
  and refactor tooling now treat the symbol consistently with
  every other `isDo…` getter on `HtmlMinInterface`.
- `make bench-quick` now writes to
  `benchmarks/build/quick-report.md` instead of the published
  `latest.md`. Quick local iteration loops can no longer
  accidentally publish 2-iteration noise.
- README and `CHANGELOG` updated to describe the actual two-method
  `DomObserver` interface
  (`domElementBeforeMinification` + `domElementAfterMinification`).
  Docs still referenced the old single `notifyDomNodeManipulationEvent()`
  hook from before the v1.0.0 split.

### Removed

- **BREAKING.** `setDomainsToRemoveHttpPrefixFromAttributes()`,
  `getDomainsToRemoveHttpPrefixFromAttributes()`, the corresponding
  field, and the `HtmlMinInterface` entry. No observer or pipeline stage ever
  read the domain list, so the public setter mutated state nothing consumed.
- `.github/dependabot.yml`. Renovate (already present) is the
  single source of truth and auto-discovers
  `benchmarks/composer.json` via the `config:base` preset; the
  Dependabot config covered only `directory: "/"` and missed
  `benchmarks/` entirely.

### Fixed

- Per-call state reset in `HtmlMin::minify()` — `isHTML4`,
  `isXHTML`, `withDocType`, and `protected_tags_counter` no longer
  leak between successive `minify()` calls on the same instance.
  Calling `minify()` once with an XHTML doctype and again with an
  HTML5 input could previously serialize the second call with
  XHTML-mode rules.

### Security

- Same-domain link rewriting in
  `OptimizeAttributes::doMakeSameDomainsLinksRelative` no longer
  matches lookalike domains. The boundary regex changed from
  `(?!\w)` (which permitted `.` and `-`) to `(?=[\/:?#]|$)`, so
  `<a href="http://example.com.evil.com/path">` is no longer
  rewritten as `<a href="/path">` when `example.com` is on the
  local-domain list.

## [1.2.0] — 2026-04-25

### Changed

- Performance: tightened DOM minify hot paths — faster attribute
  serialization and URL attribute rewriting. No API changes;
  compression ratios are byte-identical.

### Added

- Memory usage column in the benchmark Peak Memory table.
- Regression coverage for in-place sorted attribute updates.
- Docker-based Markdown formatting checks (`make md` / `make md-check`)
  in CI.

### Changed (build artefacts)

- Generated benchmark report moved to repo-root `latest.md`.

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

First stable release with a native `\DOMDocument` backend and a modernized type
surface.

### Added

- `Akankov\HtmlMin\Internal\HtmlParser` — a native `\DOMDocument` + `\DOMXPath`
  adapter for parser and DOM traversal work.
- GitHub Actions CI matrix on PHP 8.3 / 8.4 / 8.5.
- PHPStan analysis at `level: max`.
- Phan static analysis (via `ext-ast`) in CI.
- PHP-CS-Fixer code-style enforcement.
- Migration notes for the package and namespace changes.
- Makefile with `install`, `update`, `outdated`, `test`, `test-all`,
  `phpstan`, `phan`, `cs`, `rector`, `quality`, `ci`, `clean` targets.
- Dependabot configuration for `composer` and `github-actions` ecosystems.

### Changed

- `DomObserver` (renamed from `HtmlMinDomObserverInterface`) replaces
  the old single `notifyDomNodeManipulationEvent()` hook with two lifecycle
  methods, `domElementBeforeMinification()` and
  `domElementAfterMinification()`, both receiving `\DOMElement`.
- Placeholder element names switched to hyphen-safe custom-element form
  (`htmlmin-wrapper`, `htmlmin-protected`, etc.) for libxml2 ≥ 2.9.14
  compatibility — no Reflection hacks.
- Minimum PHP version: **8.3** (upstream accepted PHP 7.4).
- Tooling: PHPUnit 12, PHPStan 2.1, Rector 2.

### Removed

- The old simple-html-dom runtime dependency.
- Support for PHP < 8.3 (all EOL branches).
- Upstream's Travis/CircleCI/StyleCI configs — GitHub Actions only.

### Fixed

- PHP 8.5 deprecations (`SplObjectStorage::attach`, nullable `parentNode`).
- libxml2 ≥ 2.9.14 rejecting placeholder element names starting with `_`.

[#5]: https://github.com/akankov/html-min/pull/5
[#8]: https://github.com/akankov/html-min/pull/8
[1.0.0]: https://github.com/akankov/html-min/releases/tag/v1.0.0
[1.1.0]: https://github.com/akankov/html-min/releases/tag/v1.1.0
[1.2.0]: https://github.com/akankov/html-min/releases/tag/v1.2.0
[2.0.0]: https://github.com/akankov/html-min/releases/tag/v2.0.0
[2.5.1]: https://github.com/akankov/html-min/releases/tag/v2.5.1
[unreleased]: https://github.com/akankov/html-min/compare/v2.5.1...HEAD

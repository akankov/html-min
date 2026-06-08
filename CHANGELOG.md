# Changelog

All notable changes to `akankov/html-min` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.9.0] — 2026-06-08

Completes the HTML5 tag-omission rule set and finishes decomposing the
minifier core. Default output gets a little smaller (more optional end tags
dropped); the more aggressive start-tag omission is opt-in. No API breakage.

### Added

- **More optional end tags omitted.** `removeOmittedHtmlTags` now also drops the
  end tags of `<thead>` (when followed by `<tbody>`/`<tfoot>`), `<tbody>` and
  `<tfoot>` (per the spec's "followed by / end of section" rules), and
  `<caption>`/`<colgroup>` (unless immediately followed by ASCII whitespace or a
  comment). Smaller table markup; well-formed output is unchanged in meaning.
- **Opt-in `<html>`/`<head>`/`<body>` start-tag omission.** New
  `removeOmittedHtmlStartTags` option (`MinifierOptions` /
  `doRemoveOmittedHtmlStartTags()`), **off by default**, also omits the
  structural _start_ tags where the HTML5 spec allows (never when the element
  has attributes). It is deliberately opt-in: far more aggressive than end-tag
  omission and, on an effectively-empty document, can reduce output to an empty
  string. Existing output is unchanged unless you enable it.

### Changed

- **Internal: `ProtectedContentManager` extraction.** The protected-content
  machinery (`<nocompress>`/`<code>` subtrees, inline `<script>`/`<style>`
  bodies, conditional / special comments) and its state — the saved-node map,
  counter, and placeholder token — move out of `HtmlMin` into a dedicated
  `Internal\ProtectedContentManager`. `HtmlMin` drops ~165 lines (1218 → 1053).
  No behaviour or API change.
- **Internal: `OptionalTagOmission` rule-dispatch refactor.** The ~280-line
  conditional end-tag boolean is replaced by a per-tag dispatch with small,
  independently-readable rule helpers. No behaviour change — the per-tag truth
  table (quirks included, e.g. `dt` grouped with `dd`) is locked by new
  characterization tests in `OptionalTagOmissionTest`.

## [2.8.1] — 2026-06-07

Internal hardening of the libxml entity-preserving placeholder layer. No change
to public API or to the minified output of any input.

### Changed

- **Per-run placeholder nonce.** `HtmlParser::reset()` now regenerates the
  random placeholder nonce (and every cache derived from it) at the start of each
  minify() run instead of reusing one nonce for the whole process. This keeps the
  unguessable tokens from being reused across calls — defence-in-depth for
  long-lived / worker runtimes (Swoole, FrankenPHP, ReactPHP). All five
  nonce-derived caches are cleared together so masking and restoration never
  mismatch.
- **`HtmlParser::$brokenHtmlMap` is now private.** It was `public static` but is
  only used internally; encapsulating it prevents external code from corrupting
  the cross-call replacement table.

## [2.8.0] — 2026-05-31

Test-effectiveness and parser-cleanup release. Mutation score is raised to
**77.4%** (line coverage stays 100%) by pinning the _scoping_ of the
attribute-removal rules, and a redundant `</html>`-trim preprocessing step is
removed from the parser. The version is a **minor** bump rather than a patch
because that cleanup changes observable output for one class of **malformed**
input (multiple `</html>` markers) — well-formed documents are unaffected.
Released from PRs
[#35](https://github.com/akankov/html-min/pull/35)–[#36](https://github.com/akankov/html-min/pull/36).

### Changed

- **Mutation score raised to ~77%** (MSI 76.3% → 76.8%) by adding behavioral
  tests that pin the _scoping_ of the deprecated/default-attribute removals —
  e.g. `type=text/css` is stripped only on `rel=stylesheet` links (not
  `rel=preload`), and `value=""` only on `<input type=text>` (not other input
  types). The Infection floor is ratcheted 74 → 75 to match (a ~1.75pp buffer is
  kept for timeout jitter). Audit note: the bulk of the remaining surviving
  mutants are genuinely equivalent — internal cache keys, performance
  short-circuits, `(string)` casts that never see `null`, and regex changes made
  unreachable by upstream `stripos()` guards — so they are intentionally not
  chased. No public API or runtime behaviour change.
- **Removed a redundant `</html>`-trim preprocessing step** in `HtmlParser::parse()`.
  libxml already pulls content after a closing `</html>` back into the body, so
  the explicit regex + `str_replace` was a no-op for every well-formed and
  single-`</html>` input (verified byte-identical on PHP 8.3/8.4/8.5). The only
  observable difference is on pathological input with **multiple** `</html>`
  markers (`</html>mid</html>end`), which now flattens to a single paragraph
  (`<p>midend`) instead of two — both are arbitrary handling of malformed HTML;
  the new behaviour is pinned by a characterization test. This also deletes a
  cluster of equivalent mutants and a confusing regex. (The sibling
  pre-`<!DOCTYPE>` junk strip was investigated too and is **not** redundant —
  libxml keeps element junk before the doctype — so it stays.)

## [2.7.1] — 2026-05-31

Test-hardening and robustness release. The library test suite now covers **100%
of lines** (up from ~93%) with the CI floors ratcheted to match, and two small
warning-suppression / dead-guard touches fell out of the coverage work. No
public API or runtime behaviour change. Released from PRs
[#31](https://github.com/akankov/html-min/pull/31)–[#33](https://github.com/akankov/html-min/pull/33).

### Changed

- **`HtmlParser::findAll()` no longer leaks a PHP warning** for an unsupported
  selector (e.g. a CSS class like `.foo`, which has no XPath translation). The
  malformed-query case already returned an empty array; the raw `DOMXPath`
  warning is now suppressed so it stays a clean empty result.
- **CLI input reading simplified.** `Cli::readInput()` now relies on a single
  `@file_get_contents()` (which already reports missing / unreadable / directory
  paths via `false`) instead of a redundant `is_file()`/`is_readable()`
  pre-check. Behaviour is unchanged — same stderr message and exit code 1 on
  failure. Raw PHP warnings on I/O failure are suppressed (the clean stderr line
  is the signal).
- **Test suite now covers 100% of library lines** (up from ~93%). Reaching the
  last few percent surfaced two small robustness touches (the `findAll()`
  warning suppression and the `Cli` read simplification above) and several
  defensive-guard simplifications; remaining unreachable type-guards were
  restructured rather than ignored. The CI floors are ratcheted to match:
  `MIN_LINE_COVERAGE` 90 → 100, and the Infection MSI / Covered-MSI floors
  72 → 74. No public API or runtime behaviour change.

## [2.7.0] — 2026-05-30

Extensibility and internal-architecture release. Every library class is now open
for subclassing, and the ~2,000-line `HtmlMin` god class is decomposed into five
focused `Internal` collaborators — all behaviour-preserving, no public API
change. Also fixes an adversarial-input placeholder collision and adds a
test-effectiveness toolchain (line-coverage gate + Infection mutation testing,
with Codecov and Stryker badges). Released from PRs
[#24](https://github.com/akankov/html-min/pull/24)–[#29](https://github.com/akankov/html-min/pull/29).

### Fixed

- **Adversarial input that collides with internal placeholders no longer
  corrupts output.** The parser masks libxml-hostile characters (`&|+%@[]{}`)
  with placeholder tokens and reverses them after serialization; caller content
  that literally contained a token (e.g. `____HTMLMIN_AMP____`) was rewritten by
  the restore pass (→ `&`). Placeholders now embed a per-process random nonce,
  so input cannot collide with them. Internal — entity/AMP/template round-trips
  are unchanged; only the obscure collision case is fixed.

### Changed

- **Library classes are no longer `final`.** Every class is now open for
  extension (`final` and the `final readonly class` form are removed throughout),
  since consumers of a library legitimately need to subclass. Value objects
  (`MinifierOptions`, `Cli`, `MinifierMiddleware`, `DomSerializer`) keep
  **property-level `readonly`** — they stay immutable but are freely
  subclassable (unlike a `readonly class`, which only accepts readonly
  subclasses). Rector's `ReadOnlyClassRector` is disabled to preserve this.
- **Decomposing the `HtmlMin` god class** (2,023 → 1,220 lines so far), in
  behaviour-preserving slices:
    - HTML5 optional-end-tag rules — the 280-line `domNodeClosingTagOptional()`
      plus its tag lists and memo cache → `Internal\OptionalTagOmission`.
    - DOM → HTML5 serialization — `domNodeToString()` / attribute-string
      building / whitespace helpers → `Internal\DomSerializer`, which reads its
      flags through the existing `HtmlMinInterface` config contract.
    - Opt-in inline CSS/JS minification — the `<style>`/`<script>` coordination,
      pluggable-override storage, and bundled-minifier fallback →
      `Internal\InlineContentMinifier`.
    - Whitespace-collapsing passes — `removeWhitespaceAroundTags()` /
      `sumUpWhitespace()` and their tag tables → `Internal\WhitespaceNormalizer`
      (config-free static transforms; the toggles still gate them at the call site).
    - Doctype string building — `getDoctype()` → `Internal\Doctype::serialize()`,
      sitting next to the `Internal\DoctypeKind` classifier.
      The protected `getNextSiblingOfTypeDOMElement()` and `domNodeToString()` are
      kept as delegating shims so existing subclasses do not break. Internal — no
      public surface change.
- **CI now measures test effectiveness.** Added a coverage gate (line coverage
  floor, enforced via `bin/coverage-check.php`) and mutation testing with
  Infection (MSI floor). Both run in `make ci` and a new CI job, backed by a
  pcov Docker image (`docker/coverage.Dockerfile`). Dev-only — no change to the
  published package or its runtime dependencies.
- **Hardened the inline-minifier test assertions.** Added boundary-exact
  characterization tests for the CSS/JS scanners (comment separation, `url(`
  lookahead, regex-vs-division at each character boundary, template
  interpolation). Test-only — no runtime behaviour change.
- **Hardened the attribute-removal test assertions.** Added the negative
  complement of the default-attribute rules — near-misses (wrong value, or the
  right attribute on the wrong tag) that must be kept — plus the media/`type`
  CSS-default removals, pinning every rule's `tag && attr && value` conjunction.
  Test-only — no runtime behaviour change.
- **Data-driven default-attribute removals.** Replaced the 13 repetitive
  `if ($tag === … && $attrName === … && $attrValue === …)` blocks in
  `OptimizeAttributes` with two declarative lookup tables. Behaviour is
  identical (guarded by the rule matrix above); ~50 lines of branching become a
  table plus two lookups. The mutation floor moves to 72% MSI — collapsing the
  if-chain removes ~90 thoroughly-killed mutants, which mechanically lowers the
  score even though the code is simpler. Internal — no public surface change.
- **Coverage and mutation badges.** CI uploads line coverage to Codecov and the
  MSI to the Stryker Mutator dashboard; the README shows both badges. Reporting
  only — gated behind repository secrets and a no-op without them.

## [2.6.1] — 2026-05-29

Bug-fix release hardening the v2.6.0 bundled inline minifiers against three
edge cases that could corrupt output. All three are narrow but real; the
default-off toggles mean only opted-in callers were affected.

### Fixed

- **Inline CSS: a literal `;}` inside a string is no longer mangled.** The
  trailing-semicolon optimisation ran as a blanket `str_replace(';}', '}')`
  over the whole output, reaching into verbatim-preserved strings — e.g.
  `content:"x;}y"` became `content:"x}y"`. The `;` is now deferred and dropped
  only when it is genuinely structural (immediately before `}`).
- **Inline JS: `/` after a postfix `++`/`--` is treated as division, not a
  regex.** `a++ / b` was scanned as a regex literal, swallowing the rest of the
  line and suppressing whitespace collapse. `a + /re/` remains a regex.
- **Inline JS: braces inside strings/nested templates within a `${…}`
  interpolation no longer break template scanning.** A construct like
  `` `${ obj["}"] }` `` previously miscounted interpolation depth, missed the
  closing backtick, and ran past the template. Strings and nested templates are
  now skipped while counting interpolation braces.

## [2.6.0] — 2026-05-28

Inline CSS and JS minification lands as an opt-in feature. The contents of
inline `<style>` and `<script>` blocks — previously passed through untouched —
can now be minified by bundled, zero-dependency, conservative minifiers, with
pluggable backends for aggressive tools. Both toggles default to off, so
existing output is unchanged. Released from PR
[#17](https://github.com/akankov/html-min/pull/17).

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

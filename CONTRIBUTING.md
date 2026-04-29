# Contributing

Thanks for helping improve `akankov/html-min`. This project is a small PHP
library, so focused changes with clear tests are easiest to review.

## Reporting Bugs

Open a bug report when minification changes HTML semantics, throws unexpectedly,
or regresses output size or runtime.

Include:

- The package version or commit SHA.
- The PHP version and relevant extensions.
- The smallest input HTML that reproduces the issue.
- The minifier options used, if not the defaults.
- The actual output and expected output.
- Any related parser warnings, stack traces, or benchmark numbers.

Please do not report security vulnerabilities in public issues. Use the process
in [SECURITY.md](SECURITY.md) instead.

## Requesting Features

Feature requests should describe the HTML pattern, the expected output, and why
the behavior belongs in this library rather than in caller code. If the change
could affect existing minified output, call that out clearly.

## Development Setup

Install dependencies from the repository root:

```bash
composer install
```

Useful local checks:

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/rector process --dry-run
```

Run `make test-all` when changing parser or minification behavior. It runs the
PHPUnit suite against the supported PHP matrix.

Markdown documentation is formatted with Dockerized Prettier:

```bash
make md-check
```

## Tests

Add regression coverage for behavior changes. For minification input/output
cases, prefer fixtures in `tests/fixtures/` named `<name>.html` and
`<name>_result.html`, then add a PHPUnit data-provider case.

Keep tests compatible with PHP 8.3. The Composer platform and Rector config are
pinned to PHP 8.3 even when local checks also run newer versions.

## Benchmarks

Benchmark code lives in the separate Composer project under `benchmarks/`. Run
`make bench-install` once, then `make bench-quick` for a local regression loop.

Do not hand-edit `latest.md`; regenerate it with `make bench` or
`make bench-quick`.

## Pull Requests

Before opening a pull request:

- Keep the change focused and explain the user-visible behavior.
- Add or update tests for parser and minification regressions.
- Update documentation when configuration or behavior changes.
- Run the relevant checks and mention any that could not be run.
- Link the related issue when there is one.

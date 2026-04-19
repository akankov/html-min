# Security Policy

## Supported Versions

Security fixes land on the latest `1.x` release. Older majors will only
be patched once a `2.x` exists, and only for critical issues.

| Version | Supported |
|---------|-----------|
| 1.x     | ✅         |
| < 1.0   | ❌         |

## Reporting a Vulnerability

**Please do not open a public GitHub issue for security problems.**

Report vulnerabilities privately via GitHub's private reporting:

1. Go to the repo's [Security tab](https://github.com/akankov/html-min/security).
2. Click **Report a vulnerability**.
3. Describe the issue, affected versions, and a proof-of-concept if you
   have one.

If GitHub's private reporting is unavailable to you, email
<akankov@gmail.com> instead.

## What to expect

- **Acknowledgement**: within 5 business days.
- **Triage & severity assessment**: within 10 business days.
- **Fix timeline**: depends on severity. Critical issues get a patch
  release as soon as a fix is verified; low-severity issues may be
  bundled into the next regular release.
- **Disclosure**: coordinated. We'll publish a GitHub Security Advisory
  (GHSA) crediting the reporter once a fix is released, unless you
  request otherwise.

## Scope

Findings in scope:

- Parser/minifier behavior that breaks HTML semantics in a
  security-relevant way (e.g. HTML injection, XSS escape bypass, DOM
  clobbering surfaces).
- Denial-of-service via pathological input (catastrophic regex,
  exponential blowup, unbounded memory).
- Vulnerabilities in runtime dependencies that this library exposes.

Out of scope:

- Issues in `voku/HtmlMin` upstream (report those to
  [voku/HtmlMin](https://github.com/voku/HtmlMin)).
- Issues that require a malicious maintainer to already be running code
  on your system.
- Findings in the dev-only toolchain (PHPUnit, PHPStan, etc.) unless
  they affect library output.

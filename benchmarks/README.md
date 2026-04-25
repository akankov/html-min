# html-min benchmarks

Measures `akankov/html-min` against four other PHP HTML minifiers on speed and compression ratio.
Results are published to `../latest.md` by `make bench` run from the library root.

## Run

```bash
make bench-install   # install dependencies once (~1 minute)
make bench           # full run (~5-10 minutes, writes latest.md)
make bench-quick     # faster run with fewer iterations, for local regression checks
```

## Reproduce

Real-world fixtures under `fixtures/real-world/` are frozen snapshots — see `SOURCES.md` for provenance.
PHP version, host, git SHA, and adapter versions are stamped into every generated report.

## Caveats

- Single-threaded, single-process PHP. Matches typical deployment, but not the only one.
- Every adapter runs with **default configuration**. No per-adapter tuning.
- `abordage/html-min` is labelled "regex-based (unsafe reference)" — it skips HTML parsing,
  which is a different safety class from the other four.
- `abordage/html-min`'s default config short-circuits if `<!DOCTYPE` isn't within the first 100 bytes of input — it returns the input unchanged. All real-world benchmark fixtures include a DOCTYPE, so this doesn't affect published numbers; fragments or DOCTYPE-less inputs would produce a misleading 1.00 ratio for this adapter.
- Numbers are for this corpus on your hardware. Ratios between adapters are the signal.

## Latest results

See [`../latest.md`](../latest.md) for the most recent published
numbers, including host/PHP version provenance.

## Updating the report

1. Make sure you're on a clean commit — the report header captures the git SHA.
2. `make bench` — writes `latest.md`.
3. `git add latest.md && git commit -m "docs(benchmarks): refresh"`.

Do not hand-edit `latest.md`. It is regenerated on every run.

# html-min benchmarks

Measures `akankov/html-min` against four other PHP HTML minifiers on speed and compression ratio.
Results are published to `../docs/benchmarks/latest.md` by `make bench` run from the library root.

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
- Numbers are for this corpus on your hardware. Ratios between adapters are the signal.

# html-min benchmarks

Generated: 2026-05-28T07:14:04+00:00
Host: Linux 6.12.76-linuxkit / PHP 8.4.20 / git 0fcd6b8 (dirty: based on uncommitted source)

**Adapter versions:**
- `akankov/html-min` dev-feat/benchmarks
- `akankov/html-min (inline)` dev-feat/benchmarks
- `voku/html-min` 4.5.1
- `wyrihaximus/html-compress` 4.4.0
- `zaininnari/html-minifier` 0.4.2
- `abordage/html-min` 1.0.0 _(regex-based, unsafe reference)_

## Summary

| adapter | median ms/op | geomean ms/op | parse failures | avg gzipped ratio |
|---|---|---|---|---|
| akankov/html-min | 1.9 | 1.9 | 0 / 15 | 90.7% |
| akankov/html-min (inline) | 2.1 | 2.5 | 0 / 15 | 87.6% |
| voku/html-min | 3.3 | 3.6 | 0 / 15 | 90.7% |
| wyrihaximus/html-compress | 6.0 | 7.5 | 0 / 15 | **87.0%** |
| zaininnari/html-minifier | 9.5 | 8.3 | 0 / 15 | 94.8% |
| abordage/html-min † | **0.2** | **0.2** | 0 / 15 | 90.2% |

## Speed (ms/op, lower is better)

| adapter | base1 | base2 | base3 | base4 | code | hlt | blog-post | bootstrap-docs | html-email | inline-heavy-landing | marketing-page | wikipedia-article | repeated-fragments | deep-nesting | attribute-heavy |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| akankov/html-min | 0.3 ± 0.0 | 0.3 ± 0.0 | 0.2 ± 0.0 | 1.9 ± 0.0 | 0.1 ± 0.0 | 5.6 ± 0.1 | 10.9 ± 0.1 | 5.8 ± 0.0 | 1.5 ± 0.0 | 0.2 ± 0.0 | 10.6 ± 0.2 | 65.5 ± 0.8 | 17.1 ± 0.3 | 0.7 ± 0.0 | 18.0 ± 0.2 |
| akankov/html-min (inline) | 0.4 ± 0.0 | 0.4 ± 0.0 | 0.4 ± 0.0 | 1.9 ± 0.0 | 0.1 ± 0.0 | 5.7 ± 0.0 | 18.1 ± 0.5 | 6.1 ± 0.0 | 2.1 ± 0.0 | 0.4 ± 0.0 | 17.9 ± 0.2 | 72.0 ± 1.0 | 16.9 ± 0.1 | 0.7 ± 0.0 | 17.9 ± 0.2 |
| voku/html-min | 0.5 ± 0.0 | 0.5 ± 0.0 | 0.5 ± 0.0 | 3.3 ± 0.1 | 0.1 ± 0.0 | 10.9 ± 0.1 | 18.7 ± 0.2 | 9.7 ± 0.0 | 2.7 ± 0.0 | 0.3 ± 0.0 | 17.6 ± 0.3 | 162.2 ± 1.7 | 58.6 ± 1.0 | 0.9 ± 0.0 | 24.7 ± 0.2 |
| wyrihaximus/html-compress | 2.8 ± 0.1 | 2.8 ± 0.0 | 2.8 ± 0.0 | 6.0 ± 0.0 | 0.5 ± 0.0 | 17.2 ± 0.2 | 23.9 ± 0.2 | 12.4 ± 0.1 | 3.7 ± 0.1 | 1.4 ± 0.0 | 22.6 ± 0.4 | 179.2 ± 2.5 | 61.0 ± 0.9 | 1.1 ± 0.0 | 25.0 ± 0.1 |
| zaininnari/html-minifier | 1.1 ± 0.0 | 1.1 ± 0.0 | 1.1 ± 0.0 | 7.0 ± 0.1 | 0.1 ± 0.0 | 23.2 ± 0.0 | 50.2 ± 0.4 | 24.6 ± 0.1 | 9.5 ± 0.0 | 0.7 ± 0.0 | 53.0 ± 0.5 | 248.7 ± 0.9 | 48.5 ± 0.1 | 3.6 ± 0.0 | 102.3 ± 0.9 |
| abordage/html-min † | **0.0 ± 0.0** | **0.0 ± 0.0** | **0.0 ± 0.0** | **0.1 ± 0.0** | **0.0 ± 0.0** | **0.5 ± 0.0** | **1.2 ± 0.0** | **0.6 ± 0.0** | **0.2 ± 0.0** | **0.0 ± 0.0** | **0.9 ± 0.0** | **6.2 ± 0.0** | **0.8 ± 0.0** | **0.0 ± 0.0** | **1.2 ± 0.0** |

## Peak Memory (MiB, lower is better)

| adapter | base1 | base2 | base3 | base4 | code | hlt | blog-post | bootstrap-docs | html-email | inline-heavy-landing | marketing-page | wikipedia-article | repeated-fragments | deep-nesting | attribute-heavy |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| akankov/html-min | **6.3 MiB** | **6.3 MiB** | **6.3 MiB** | **6.3 MiB** | **6.3 MiB** | 6.5 MiB | 7.0 MiB | 6.4 MiB | 6.3 MiB | **6.3 MiB** | 7.1 MiB | 9.8 MiB | 6.9 MiB | 6.5 MiB | 6.8 MiB |
| akankov/html-min (inline) | 6.3 MiB | 6.3 MiB | 6.3 MiB | 6.3 MiB | 6.3 MiB | 6.5 MiB | 7.0 MiB | 6.4 MiB | 6.3 MiB | 6.3 MiB | 7.1 MiB | 9.8 MiB | 6.9 MiB | 6.5 MiB | 6.8 MiB |
| voku/html-min | 6.9 MiB | 6.9 MiB | 6.9 MiB | 6.9 MiB | 6.9 MiB | 7.2 MiB | 7.6 MiB | 7.1 MiB | 7.0 MiB | 6.9 MiB | 7.6 MiB | 10.8 MiB | 8.2 MiB | 7.2 MiB | 7.5 MiB |
| wyrihaximus/html-compress | 8.0 MiB | 8.0 MiB | 8.0 MiB | 8.3 MiB | 7.2 MiB | 9.6 MiB | 8.3 MiB | 8.1 MiB | 7.0 MiB | 7.4 MiB | 8.2 MiB | 12.1 MiB | 8.2 MiB | 7.2 MiB | 7.5 MiB |
| zaininnari/html-minifier | 6.8 MiB | 6.8 MiB | 6.8 MiB | 7.1 MiB | 6.8 MiB | 8.0 MiB | 9.3 MiB | 8.2 MiB | 6.9 MiB | 6.8 MiB | 9.2 MiB | 22.8 MiB | 11.7 MiB | 7.0 MiB | 12.1 MiB |
| abordage/html-min † | **6.3 MiB** | **6.3 MiB** | **6.3 MiB** | **6.3 MiB** | **6.3 MiB** | **6.3 MiB** | **6.7 MiB** | **6.3 MiB** | **6.3 MiB** | **6.3 MiB** | **6.8 MiB** | **9.4 MiB** | **6.3 MiB** | **6.3 MiB** | **6.4 MiB** |

## Compression (gzipped ratio, lower is better)

| adapter | base1 | base2 | base3 | base4 | code | hlt | blog-post | bootstrap-docs | html-email | inline-heavy-landing | marketing-page | wikipedia-article | repeated-fragments | deep-nesting | attribute-heavy |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| akankov/html-min | 88.6% (raw 83.4%) | 91.1% (raw 89.0%) | 90.7% (raw 89.4%) | **95.4% (raw 78.9%)** | **92.6% (raw 91.6%)** | 91.7% (raw 72.6%) | 85.1% (raw 76.1%) | 99.0% (raw 96.0%) | 75.3% (raw 65.2%) | 97.5% (raw 96.0%) | 90.6% (raw 78.5%) | 99.5% (raw 95.8%) | **99.1% (raw 94.9%)** | **66.0% (raw 25.6%)** | **97.9% (raw 92.8%)** |
| akankov/html-min (inline) | 80.6% (raw 73.7%) | 85.9% (raw 82.7%) | 83.7% (raw 81.2%) | **95.4% (raw 78.9%)** | **92.6% (raw 91.6%)** | 91.6% (raw 72.5%) | 85.1% (raw 76.1%) | 99.0% (raw 96.0%) | 58.7% (raw 53.3%) | 88.8% (raw 74.2%) | 90.6% (raw 78.5%) | 99.5% (raw 95.8%) | **99.1% (raw 94.9%)** | **66.0% (raw 25.6%)** | **97.9% (raw 92.8%)** |
| voku/html-min | 88.6% (raw 83.4%) | 91.1% (raw 89.0%) | 90.5% (raw 89.0%) | 95.5% (raw 78.9%) | **92.6% (raw 91.6%)** | 91.8% (raw 72.6%) | 85.1% (raw 76.1%) | 98.9% (raw 96.0%) | 75.3% (raw 65.2%) | 97.5% (raw 96.0%) | 90.6% (raw 78.5%) | 99.6% (raw 95.8%) | **99.1% (raw 94.9%)** | **66.0% (raw 25.6%)** | **97.9% (raw 92.8%)** |
| wyrihaximus/html-compress | 78.6% (raw 70.7%) | 84.0% (raw 79.7%) | 81.4% (raw 77.7%) | 95.4% (raw 78.9%) | **92.6% (raw 91.6%)** | **91.2% (raw 71.8%)** | **84.9% (raw 76.0%)** | **98.9% (raw 95.9%)** | **57.6% (raw 50.8%)** | **86.8% (raw 70.8%)** | **90.5% (raw 78.4%)** | 99.5% (raw 95.6%) | **99.1% (raw 94.9%)** | **66.0% (raw 25.6%)** | **97.9% (raw 92.8%)** |
| zaininnari/html-minifier | 92.2% (raw 91.1%) | 92.8% (raw 92.5%) | 92.2% (raw 92.2%) | 96.9% (raw 88.5%) | 99.5% (raw 99.8%) | 92.9% (raw 77.3%) | 88.0% (raw 87.5%) | 99.9% (raw 100.0%) | 75.3% (raw 68.8%) | 99.2% (raw 98.7%) | 93.6% (raw 89.4%) | 99.0% (raw 99.4%) | 100.0% (raw 100.0%) | 100.0% (raw 100.0%) | 100.0% (raw 100.0%) |
| abordage/html-min † | **73.6% (raw 70.0%)** | **74.0% (raw 71.9%)** | **74.2% (raw 72.3%)** | 96.3% (raw 87.4%) | 99.0% (raw 98.9%) | 92.7% (raw 78.2%) | 85.5% (raw 78.6%) | 99.1% (raw 98.4%) | 71.2% (raw 61.0%) | 97.2% (raw 89.7%) | 91.6% (raw 81.4%) | **98.8% (raw 99.0%)** | 100.0% (raw 100.0%) | 100.0% (raw 100.0%) | 100.0% (raw 100.0%) |

## Methodology

- Default configuration for every adapter. No per-adapter tuning.
- Same input bytes. UTF-8 throughout.
- Single-threaded, single-process PHP.
- No forced GC between runs (PHPBench default).
- Speed measured via PHPBench: 1 warmup revolution, 10 revolutions × 5 iterations.
- Peak memory comes from PHPBench's per-iteration `mem-peak`, reported as the max peak resident allocation observed for each (adapter, fixture) case.
- Compression measured separately by running each adapter once per fixture and measuring output via `gzencode($out, 9)`.
- Every output is round-tripped through `DOMDocument::loadHTML`; cells marked `n/a†` failed this check.
- † marks adapters flagged as **regex-based (unsafe reference)**: `abordage/html-min`. Their speed numbers are informative but the comparison class is asymmetric — they skip structural HTML parsing.

## Non-claims

- Not a correctness judgement beyond DOM round-trip parseability.
- Results are for this corpus on this host. Ratios between adapters are the meaningful signal.

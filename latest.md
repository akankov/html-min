# html-min benchmarks

Generated: 2026-06-30T08:05:23+00:00
Host: Linux 6.12.76-linuxkit / PHP 8.4.20 / git a649466

**Adapter versions:**
- `akankov/html-min` dev-chore/bench-drop-wyrihaximus-voku5
- `akankov/html-min (inline)` dev-chore/bench-drop-wyrihaximus-voku5
- `voku/html-min` 5.0.0
- `zaininnari/html-minifier` 0.4.2
- `abordage/html-min` 1.0.0 _(regex-based, unsafe reference)_

## Summary

| adapter | median ms/op | geomean ms/op | parse failures | avg gzipped ratio |
|---|---|---|---|---|
| akankov/html-min | 2.0 | 2.1 | 0 / 15 | 90.7% |
| akankov/html-min (inline) | 2.2 | 2.7 | 0 / 15 | **87.6%** |
| voku/html-min | 3.4 | 3.8 | 0 / 15 | 90.2% |
| zaininnari/html-minifier | 9.5 | 8.2 | 0 / 15 | 94.8% |
| abordage/html-min † | **0.2** | **0.2** | 0 / 15 | 90.2% |

## Speed (ms/op, lower is better)

| adapter | base1 | base2 | base3 | base4 | code | hlt | blog-post | bootstrap-docs | html-email | inline-heavy-landing | marketing-page | wikipedia-article | repeated-fragments | deep-nesting | attribute-heavy |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| akankov/html-min | 0.3 ± 0.0 | 0.3 ± 0.0 | 0.3 ± 0.0 | 2.0 ± 0.0 | 0.1 ± 0.0 | 5.7 ± 0.0 | 11.2 ± 0.1 | 6.0 ± 0.0 | 1.6 ± 0.0 | 0.2 ± 0.0 | 11.0 ± 0.1 | 70.0 ± 0.8 | 18.0 ± 0.1 | 0.7 ± 0.0 | 18.0 ± 0.0 |
| akankov/html-min (inline) | 0.4 ± 0.0 | 0.4 ± 0.0 | 0.4 ± 0.0 | 2.0 ± 0.0 | 0.1 ± 0.0 | 6.2 ± 0.2 | 19.1 ± 0.1 | 6.3 ± 0.0 | 2.2 ± 0.0 | 0.5 ± 0.0 | 18.9 ± 0.1 | 74.9 ± 0.3 | 18.0 ± 0.2 | 0.7 ± 0.0 | 17.9 ± 0.1 |
| voku/html-min | 0.5 ± 0.0 | 0.6 ± 0.0 | 0.6 ± 0.0 | 3.4 ± 0.0 | 0.1 ± 0.0 | 11.8 ± 0.1 | 19.7 ± 0.3 | 10.7 ± 0.0 | 2.9 ± 0.0 | 0.3 ± 0.0 | 19.0 ± 0.1 | 164.0 ± 2.5 | 60.0 ± 0.5 | 1.0 ± 0.0 | 24.8 ± 0.1 |
| zaininnari/html-minifier | 1.1 ± 0.0 | 1.1 ± 0.0 | 1.1 ± 0.0 | 7.0 ± 0.0 | 0.1 ± 0.0 | 23.2 ± 0.1 | 49.9 ± 0.1 | 25.0 ± 0.3 | 9.5 ± 0.2 | 0.7 ± 0.0 | 52.8 ± 0.4 | 248.8 ± 1.3 | 48.7 ± 0.8 | 3.6 ± 0.0 | 102.0 ± 0.7 |
| abordage/html-min † | **0.0 ± 0.0** | **0.0 ± 0.0** | **0.0 ± 0.0** | **0.1 ± 0.0** | **0.0 ± 0.0** | **0.5 ± 0.0** | **1.2 ± 0.0** | **0.6 ± 0.0** | **0.2 ± 0.0** | **0.0 ± 0.0** | **0.9 ± 0.0** | **6.3 ± 0.0** | **0.7 ± 0.0** | **0.0 ± 0.0** | **1.3 ± 0.0** |

## Peak Memory (MiB, lower is better)

| adapter | base1 | base2 | base3 | base4 | code | hlt | blog-post | bootstrap-docs | html-email | inline-heavy-landing | marketing-page | wikipedia-article | repeated-fragments | deep-nesting | attribute-heavy |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| akankov/html-min | **4.0 MiB** | **4.0 MiB** | **4.0 MiB** | 4.0 MiB | **4.0 MiB** | 4.2 MiB | 4.7 MiB | 4.1 MiB | 4.0 MiB | **4.0 MiB** | 4.8 MiB | 8.0 MiB | 4.6 MiB | 4.2 MiB | 4.5 MiB |
| akankov/html-min (inline) | 4.0 MiB | 4.0 MiB | 4.0 MiB | 4.0 MiB | 4.0 MiB | 4.2 MiB | 4.8 MiB | 4.1 MiB | 4.0 MiB | 4.0 MiB | 4.8 MiB | 8.0 MiB | 4.6 MiB | 4.2 MiB | 4.5 MiB |
| voku/html-min | 4.6 MiB | 4.6 MiB | 4.6 MiB | 4.7 MiB | 4.6 MiB | 4.9 MiB | 5.3 MiB | 4.8 MiB | 4.7 MiB | 4.6 MiB | 5.3 MiB | 7.6 MiB | 6.0 MiB | 4.9 MiB | 5.2 MiB |
| zaininnari/html-minifier | 4.5 MiB | 4.5 MiB | 4.5 MiB | 4.8 MiB | 4.5 MiB | 5.6 MiB | 7.0 MiB | 5.8 MiB | 4.6 MiB | 4.5 MiB | 6.9 MiB | 20.5 MiB | 9.3 MiB | 4.7 MiB | 9.8 MiB |
| abordage/html-min † | **4.0 MiB** | **4.0 MiB** | **4.0 MiB** | **4.0 MiB** | **4.0 MiB** | **4.0 MiB** | **4.4 MiB** | **4.0 MiB** | **4.0 MiB** | **4.0 MiB** | **4.5 MiB** | **7.1 MiB** | **4.0 MiB** | **4.0 MiB** | **4.1 MiB** |

## Compression (gzipped ratio, lower is better)

| adapter | base1 | base2 | base3 | base4 | code | hlt | blog-post | bootstrap-docs | html-email | inline-heavy-landing | marketing-page | wikipedia-article | repeated-fragments | deep-nesting | attribute-heavy |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| akankov/html-min | 88.6% (raw 83.4%) | 91.1% (raw 89.0%) | 90.7% (raw 89.4%) | **95.4% (raw 78.9%)** | 92.6% (raw 91.6%) | 91.7% (raw 72.6%) | 85.1% (raw 76.1%) | **98.9% (raw 96.0%)** | 75.3% (raw 65.2%) | 97.5% (raw 96.0%) | 90.6% (raw 78.5%) | 99.5% (raw 95.8%) | 99.1% (raw 94.9%) | 66.0% (raw 25.6%) | 97.9% (raw 92.8%) |
| akankov/html-min (inline) | 80.6% (raw 73.7%) | 85.9% (raw 82.7%) | 83.7% (raw 81.2%) | **95.4% (raw 78.9%)** | 92.6% (raw 91.6%) | **91.6% (raw 72.5%)** | **85.0% (raw 76.1%)** | **98.9% (raw 96.0%)** | **58.7% (raw 53.3%)** | **88.8% (raw 74.2%)** | **90.6% (raw 78.5%)** | 99.5% (raw 95.8%) | 99.1% (raw 94.9%) | 66.0% (raw 25.6%) | 97.9% (raw 92.8%) |
| voku/html-min | 89.1% (raw 83.8%) | 91.0% (raw 88.9%) | 90.4% (raw 88.8%) | 95.6% (raw 79.4%) | **91.2% (raw 89.5%)** | 91.9% (raw 73.3%) | 85.4% (raw 76.5%) | 99.2% (raw 96.7%) | 75.9% (raw 66.7%) | 97.1% (raw 95.1%) | 90.9% (raw 79.0%) | 99.6% (raw 96.3%) | **99.0% (raw 94.9%)** | **58.3% (raw 25.5%)** | **97.9% (raw 92.8%)** |
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

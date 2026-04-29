# html-min benchmarks

Generated: 2026-04-29T08:46:41+00:00
Host: Linux 6.12.76-linuxkit / PHP 8.4.20 / git 0d20cee

**Adapter versions:**

- `akankov/html-min` dev-feat/benchmarks
- `voku/html-min` 4.5.1
- `wyrihaximus/html-compress` 4.4.0
- `zaininnari/html-minifier` 0.4.2
- `abordage/html-min` 1.0.0 _(regex-based, unsafe reference)_

## Summary

| adapter                   | median ms/op | geomean ms/op | parse failures | avg gzipped ratio |
|---------------------------|--------------|---------------|----------------|-------------------|
| akankov/html-min          | 2.1          | 1.9           | 0 / 11         | 90.9%             |
| voku/html-min             | 3.0          | 3.2           | 0 / 11         | 90.9%             |
| wyrihaximus/html-compress | 5.4          | 7.1           | 0 / 11         | **86.8%**         |
| zaininnari/html-minifier  | 9.5          | 7.5           | 0 / 11         | 92.9%             |
| abordage/html-min †       | **0.2**      | **0.2**       | 0 / 11         | 86.9%             |

## Speed (ms/op, lower is better)

| adapter                   | base1         | base2         | base3         | base4         | code          | hlt           | blog-post     | bootstrap-docs | html-email    | marketing-page | wikipedia-article |
|---------------------------|---------------|---------------|---------------|---------------|---------------|---------------|---------------|----------------|---------------|----------------|-------------------|
| akankov/html-min          | 0.3 ± 0.0     | 0.3 ± 0.0     | 0.2 ± 0.0     | 2.1 ± 0.0     | 0.1 ± 0.0     | 6.3 ± 0.1     | 11.9 ± 0.1    | 6.5 ± 0.1      | 1.7 ± 0.0     | 11.8 ± 0.1     | 76.7 ± 0.5        |
| voku/html-min             | 0.4 ± 0.0     | 0.5 ± 0.0     | 0.5 ± 0.0     | 3.0 ± 0.0     | 0.1 ± 0.0     | 10.6 ± 0.1    | 18.0 ± 0.2    | 9.4 ± 0.1      | 2.6 ± 0.0     | 17.1 ± 0.1     | 156.1 ± 0.6       |
| wyrihaximus/html-compress | 2.5 ± 0.0     | 2.6 ± 0.0     | 2.5 ± 0.0     | 5.4 ± 0.0     | 0.5 ± 0.0     | 15.8 ± 0.0    | 23.3 ± 0.3    | 11.8 ± 0.1     | 3.7 ± 0.0     | 21.6 ± 0.1     | 174.3 ± 0.7       |
| zaininnari/html-minifier  | 1.1 ± 0.0     | 1.1 ± 0.0     | 1.1 ± 0.0     | 6.9 ± 0.0     | 0.1 ± 0.0     | 23.1 ± 0.1    | 49.8 ± 0.3    | 24.8 ± 0.3     | 9.5 ± 0.0     | 52.1 ± 0.2     | 250.6 ± 2.6       |
| abordage/html-min †       | **0.0 ± 0.0** | **0.0 ± 0.0** | **0.0 ± 0.0** | **0.1 ± 0.0** | **0.0 ± 0.0** | **0.5 ± 0.0** | **1.2 ± 0.0** | **0.6 ± 0.0**  | **0.2 ± 0.0** | **0.9 ± 0.0**  | **6.3 ± 0.0**     |

## Peak Memory (MiB, lower is better)

| adapter                   | base1       | base2       | base3       | base4       | code        | hlt         | blog-post   | bootstrap-docs | html-email  | marketing-page | wikipedia-article |
|---------------------------|-------------|-------------|-------------|-------------|-------------|-------------|-------------|----------------|-------------|----------------|-------------------|
| akankov/html-min          | 5.8 MiB     | 5.8 MiB     | 5.8 MiB     | 5.8 MiB     | 5.8 MiB     | 6.0 MiB     | 6.6 MiB     | 5.9 MiB        | 5.8 MiB     | 6.6 MiB        | 9.9 MiB           |
| voku/html-min             | 6.4 MiB     | 6.4 MiB     | 6.4 MiB     | 6.5 MiB     | 6.4 MiB     | 6.7 MiB     | 7.1 MiB     | 6.7 MiB        | 6.5 MiB     | 7.1 MiB        | 10.3 MiB          |
| wyrihaximus/html-compress | 7.6 MiB     | 7.6 MiB     | 7.6 MiB     | 7.9 MiB     | 6.7 MiB     | 9.1 MiB     | 7.9 MiB     | 7.6 MiB        | 6.5 MiB     | 7.7 MiB        | 11.6 MiB          |
| zaininnari/html-minifier  | 6.3 MiB     | 6.3 MiB     | 6.3 MiB     | 6.6 MiB     | 6.3 MiB     | 7.5 MiB     | 8.8 MiB     | 7.7 MiB        | 6.4 MiB     | 8.8 MiB        | 22.3 MiB          |
| abordage/html-min †       | **5.5 MiB** | **5.5 MiB** | **5.5 MiB** | **5.6 MiB** | **5.5 MiB** | **5.8 MiB** | **6.3 MiB** | **5.7 MiB**    | **5.6 MiB** | **6.3 MiB**    | **8.9 MiB**       |

## Compression (gzipped ratio, lower is better)

| adapter                   | base1                 | base2                 | base3                 | base4                 | code                  | hlt                   | blog-post             | bootstrap-docs        | html-email            | marketing-page        | wikipedia-article     |
|---------------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|
| akankov/html-min          | 88.6% (raw 83.4%)     | 91.1% (raw 89.0%)     | 90.7% (raw 89.4%)     | **95.4% (raw 78.9%)** | **92.6% (raw 91.6%)** | 91.7% (raw 72.6%)     | 85.1% (raw 76.1%)     | 99.0% (raw 96.0%)     | 75.3% (raw 65.2%)     | 90.6% (raw 78.5%)     | 99.5% (raw 95.8%)     |
| voku/html-min             | 88.6% (raw 83.4%)     | 91.1% (raw 89.0%)     | 90.5% (raw 89.0%)     | 95.5% (raw 78.9%)     | **92.6% (raw 91.6%)** | 91.8% (raw 72.6%)     | 85.1% (raw 76.1%)     | 98.9% (raw 96.0%)     | 75.3% (raw 65.2%)     | 90.6% (raw 78.5%)     | 99.6% (raw 95.8%)     |
| wyrihaximus/html-compress | 78.6% (raw 70.7%)     | 84.0% (raw 79.7%)     | 81.4% (raw 77.7%)     | 95.4% (raw 78.9%)     | **92.6% (raw 91.6%)** | **91.2% (raw 71.8%)** | **84.9% (raw 76.0%)** | **98.9% (raw 95.9%)** | **57.6% (raw 50.8%)** | **90.5% (raw 78.4%)** | 99.5% (raw 95.6%)     |
| zaininnari/html-minifier  | 92.2% (raw 91.1%)     | 92.8% (raw 92.5%)     | 92.2% (raw 92.2%)     | 96.9% (raw 88.5%)     | 99.5% (raw 99.8%)     | 92.9% (raw 77.3%)     | 88.0% (raw 87.5%)     | 99.9% (raw 100.0%)    | 75.3% (raw 68.8%)     | 93.6% (raw 89.4%)     | 99.0% (raw 99.4%)     |
| abordage/html-min †       | **73.6% (raw 70.0%)** | **74.0% (raw 71.9%)** | **74.2% (raw 72.3%)** | 96.3% (raw 87.4%)     | 99.0% (raw 98.9%)     | 92.7% (raw 78.2%)     | 85.5% (raw 78.6%)     | 99.1% (raw 98.4%)     | 71.2% (raw 61.0%)     | 91.6% (raw 81.4%)     | **98.8% (raw 99.0%)** |

## Methodology

- Default configuration for every adapter. No per-adapter tuning.
- Same input bytes. UTF-8 throughout.
- Single-threaded, single-process PHP.
- No forced GC between runs (PHPBench default).
- Speed measured via PHPBench: 1 warmup revolution, 10 revolutions × 5 iterations.
- Peak memory comes from PHPBench's per-iteration `mem-peak`, reported as the max peak resident allocation observed for
  each (adapter, fixture) case.
- Compression measured separately by running each adapter once per fixture and measuring output via `gzencode($out, 9)`.
- Every output is round-tripped through `DOMDocument::loadHTML`; cells marked `n/a†` failed this check.
- † marks adapters flagged as **regex-based (unsafe reference)**: `abordage/html-min`. Their speed numbers are
  informative but the comparison class is asymmetric — they skip structural HTML parsing.

## Non-claims

- Not a correctness judgement beyond DOM round-trip parseability.
- Results are for this corpus on this host. Ratios between adapters are the meaningful signal.

# html-min benchmarks

Generated: 2026-04-24T21:00:03+00:00
Host: Darwin 25.4.0 / PHP 8.5.5 / git cd4906b

**Adapter versions:**

- `akankov/html-min` dev-feat/benchmarks
- `voku/html-min` 4.5.1
- `wyrihaximus/html-compress` 4.4.0
- `zaininnari/html-minifier` 0.4.2
- `abordage/html-min` 1.0.0 _(regex-based, unsafe reference)_

## Speed (ms/op, lower is better)

| adapter                   | base1         | base2         | base3         | base4         | code          | hlt           | blog-post     | bootstrap-docs | html-email    | marketing-page | wikipedia-article |
|---------------------------|---------------|---------------|---------------|---------------|---------------|---------------|---------------|----------------|---------------|----------------|-------------------|
| akankov/html-min          | 0.3 ± 0.0     | 0.3 ± 0.0     | 0.3 ± 0.0     | 2.6 ± 0.0     | 0.1 ± 0.0     | 8.0 ± 0.1     | 14.7 ± 0.1    | 8.2 ± 0.1      | 2.1 ± 0.0     | 14.6 ± 0.1     | 104.1 ± 0.4       |
| voku/html-min             | 0.5 ± 0.0     | 0.4 ± 0.0     | 0.4 ± 0.0     | 3.2 ± 0.0     | 0.1 ± 0.0     | 11.4 ± 0.0    | 19.0 ± 0.1    | 10.0 ± 0.0     | 2.8 ± 0.0     | 18.0 ± 0.1     | 164.2 ± 0.9       |
| wyrihaximus/html-compress | 1.3 ± 0.0     | 1.3 ± 0.0     | 1.3 ± 0.0     | 4.2 ± 0.0     | 0.2 ± 0.0     | 14.2 ± 0.1    | 23.0 ± 0.1    | 11.4 ± 0.1     | 3.8 ± 0.0     | 21.8 ± 0.1     | 179.1 ± 0.6       |
| zaininnari/html-minifier  | 1.0 ± 0.0     | 1.0 ± 0.0     | 1.0 ± 0.0     | 6.4 ± 0.1     | 0.1 ± 0.0     | 21.3 ± 0.3    | 44.8 ± 0.3    | 22.9 ± 0.2     | 8.6 ± 0.1     | 48.8 ± 1.1     | 240.9 ± 7.7       |
| abordage/html-min †       | **0.0 ± 0.0** | **0.0 ± 0.0** | **0.0 ± 0.0** | **0.1 ± 0.0** | **0.0 ± 0.0** | **0.5 ± 0.0** | **1.2 ± 0.0** | **0.5 ± 0.0**  | **0.2 ± 0.0** | **0.9 ± 0.0**  | **6.7 ± 0.2**     |

## Compression (gzipped ratio, lower is better)

| adapter                   | base1                 | base2                 | base3                 | base4                 | code                  | hlt                   | blog-post             | bootstrap-docs        | html-email            | marketing-page        | wikipedia-article     |
|---------------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|-----------------------|
| akankov/html-min          | 88.6% (raw 83.4%)     | 91.1% (raw 89.0%)     | 90.5% (raw 89.0%)     | **95.4% (raw 78.9%)** | **92.6% (raw 91.6%)** | 91.7% (raw 72.6%)     | 85.1% (raw 76.1%)     | 99.0% (raw 96.0%)     | 75.3% (raw 65.2%)     | 90.6% (raw 78.5%)     | 99.5% (raw 95.8%)     |
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
- Compression measured separately by running each adapter once per fixture and measuring output via `gzencode($out, 9)`.
- Every output is round-tripped through `DOMDocument::loadHTML`; cells marked `n/a†` failed this check.
- † marks adapters flagged as **regex-based (unsafe reference)**: `abordage/html-min`. Their speed numbers are
  informative but the comparison class is asymmetric — they skip structural HTML parsing.

## Non-claims

- Not a correctness judgement beyond DOM round-trip parseability.
- Results are for this corpus on this host. Ratios between adapters are the meaningful signal.

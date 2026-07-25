# PHP SQLite benchmark

This benchmark compares the repository's PDO-compatible userland SQLite
implementation, `PortLibs\LibSqlite\SQLitePDO`, with PHP's native
`pdo_sqlite` extension using the same PDO calls and SQL.

The default run:

```sh
php -d opcache.enable_cli=0 benchmarks/libsqlite/benchmark.php
```

It verifies every workload on both engines before timing, warms each engine,
adaptively batches fast operations, collects 15 samples per engine, prints an
ASCII result table, and writes self-contained HTML, JSON, and text artifacts
under `benchmarks/libsqlite/results/`.

Useful options:

```text
--quick                 5 samples, 30 ms target (smoke test)
--samples=N             measured samples per engine (default: 15)
--target-ms=N           target duration of each adaptive batch (default: 200)
--warmup=N              warmup batches per engine (default: 3)
--case=ID               run one case; may be repeated
--list                   list case IDs
--output-dir=PATH        artifact directory
--open                   open the generated HTML report on macOS
```

The report is the authoritative interpretation guide. In particular, fixture
construction is excluded from query timings unless the case explicitly says
otherwise; initial preparation is excluded only where the case card says so;
all result rows are consumed; and file-backed persistence is deliberately
excluded because the two engines do not offer durability-equivalent behavior
through this implementation.

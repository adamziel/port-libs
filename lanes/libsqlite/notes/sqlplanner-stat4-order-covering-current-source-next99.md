# SQL Planner STAT4 Order Covering Current Source Next99

This slice adds `SQLiteStat4OrderCoveringCurrentSourceNextPlan`, a bounded
native planner materializer for a copied WordPress `wp_options` query after an
`ANALYZE` refresh changes schema-cookie/STAT4/index signatures.

Behavior covered:

- Reuses accepted next94 STAT4 order-covering current-source invalidation.
- Materializes the selected current covering index into a current/next cursor
  tape with seek/stop/advance opcodes.
- Proves the covering ordered index elides `DeferredSeek` table lookup and
  `SorterOpen` temp sorting.
- Keeps fallback diagnostics when reverse ordering or missing covering columns
  require the next stage.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteStat4OrderCoveringCurrentSourceNext99Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 77 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-stat4-order-covering-current-source-next99.php
```

Non-overlap: this does not repeat batch94's STAT4 order-covering
current-source reprepare decision. It adds cursor-tape materialization and
VDBE-style table-lookup/sorter elision assertions for the selected covering
ordered index.

Dependency closure: no new support component is needed; this composes existing
native PHP planner, STAT4 sample, and index parsing helpers.

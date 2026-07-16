# bulk-upstream-veryquick-shard-expansion-dynamic-20260530T200340Z-0

Base accepted HEAD: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`

## Scope

Added `SQLiteRealUpstreamVeryquickRecursiveValuesBulkTest.php`, a real upstream
veryquick behavior shard that ports distinct SQL behavior from hydrated SQLite
upstream files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/withM.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test`

The PHP shard exercises generic `SQLiteSelectSql::execute()` behavior for
recursive CTE queue `LIMIT`/`OFFSET`, `VALUES` table sources with aliases,
multi-term ordering, `GROUP BY`, `count(*)`, `sum(...)`, and ordered aggregate
result windows over generic `walk`, `v`, and `items` inputs.

## Counts

- Before focused PASS lines in this file: `0`
- After focused PASS lines in this file: `1050`
- PASS-line delta: `+1050`
- Behavior assertions: `6300`
- Mapped denominator rows: unchanged, `1472 / 1589`
- Upstream runner pass/fail rows: unchanged; this is PHP behavior coverage, not
  runner-map admission.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVeryquickRecursiveValuesBulkTest.php`
  - Result: `1 test files, 6300 assertions, 0 failures`
  - PASS lines: `1050`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamVeryquickRecursiveValuesBulkTest.php`
  - Result: no syntax errors

## Non-Overlap

This is a new behavior-backed real upstream shard, not a suite-evidence metadata
row. It avoids the earlier bulk SELECT/WHERE shard by focusing on recursive CTE
queue limits, VALUES sources, and aggregate grouping behavior from different
upstream files. It also avoids stale `next965-980` runner rows and does not
invent `.test` script ids.

## Dependency Closure

No new support component is needed. The shard reuses existing
`SQLiteSelectSql` parser/executor behavior and the existing PHP `TestRunner`.

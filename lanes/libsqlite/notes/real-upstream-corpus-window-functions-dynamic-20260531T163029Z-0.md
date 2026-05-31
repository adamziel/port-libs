# real-upstream-corpus-window-functions-dynamic-20260531T163029Z-0

## Source truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/libsqlite/test/filterfault.test`
- Ported section: `filterfault.test 1.0`, especially the faultsim replay query:
  `SELECT sum(a) FILTER (WHERE b<5), count() FILTER (WHERE d!=c) FROM t1 GROUP BY c ORDER BY 1`

## Behavior

The PHP `SQLiteSelectSql` aggregate path rejected zero-argument `count()` before
this slice, so the upstream `count() FILTER (...)` query failed before row
execution. This patch treats `count()` as SQLite's row-count aggregate form,
equivalent to `count(*)`, and maps zero-argument `count() FILTER` through the
existing filtered wildcard-count summary path.

The related aggregate FILTER focused file also exposed that filtered `min()` /
`max()` bare-column sampling was still choosing rows rejected by the aggregate
FILTER predicate. `SQLiteGroupedAggregate` now applies the same FILTER predicate
before choosing the min/max sample row, so no-match filtered groups fall back to
the normal first group row.

The new focused test file uses generic `app_events` rows. It includes the exact
three-row upstream replay result, a non-filtered `count()` guard, and 1000
dynamic grouped replay cases that vary group keys, NULL filter inputs, filtered
sum groups, and mismatch counts.

## Evidence

- Red-before probe on accepted `f0b2ac475418f07122a8df716b30839150e74b1f`:
  `SQLite SELECT SQL aggregate count FILTER needs one argument`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamFilterFaultCountDynamic20260531Test.php`
  - `1 test files, 5004 assertions, 0 failures`
  - 1003 PASS cases
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamFilterAggregateSelectSqlDynamic20260531Test.php`
  - `1 test files, 6012 assertions, 0 failures`

## Non-overlap

This does not repeat prior accepted `filter1.test` / `filter2.test` aggregate
FILTER coverage: those slices used one-argument `count(*)` or column/scalar
aggregate arguments. This slice owns the remaining `filterfault.test 1.0`
zero-argument `count()` form under grouped FILTER replay.

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteSelectSql`,
`SQLiteGroupedAggregate`, and `SQLiteNumericAggregate` and only fills the missing
zero-argument aggregate shape.

## Final Verification

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/src/SQLiteGroupedAggregate.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamFilterFaultCountDynamic20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamFilterFaultCountDynamic20260531Test.php`
  - `1 test files, 5004 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamFilterAggregateSelectSqlDynamic20260531Test.php`
  - `1 test files, 6012 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowCorpusInventoryTest.php`
  - `1 test files, 27 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

No root harness was run for this isolated micro-slice. Next follow-up remains
additional real upstream window/filter uncovered sections outside
`filterfault.test 1.0`.

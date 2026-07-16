# real-upstream-corpus-window-functions-dynamic-20260601T140400Z-1

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`.
- Ported sections: `49.2`, `50.1-50.4`, `53.0`, `54.2`, `54.4`, `55.1`, and `56.2`.
- Scenario family: dbsqlfuzz/window regressions for nested scalar window subqueries, modulo predicates, `lead()` plus aggregate scalar subqueries, compound `UNION` filtering, empty grouped window subqueries, and compound `ORDER BY` diagnostics.

## Patch

- Added `SQLiteRealUpstreamWindow1FuzzSubqueryDynamic20260601Test.php`.
- Adds 1000 generated real-behavior cases plus exact upstream baselines and source-truth checks.
- Focused TestRunner evidence: `1 test files, 11024 assertions, 0 failures`.
- Expected `phpPass` movement in lane status: `5900081 -> 5911105` (`+11024` focused assertions).

## Non-Overlap

This slice avoids already accepted/current dynamic window coverage for `window1.test` sections `35`, `45`, `46`, `48`, `52`, `57`, `58`, `60`, `78`, and `79`, plus the existing `window2`, `window3`, `window4`, `window8`, `windowB`, and filter/fault batches. It deliberately excludes `window1.test 50.5` and `51.1`; `50.5` is a larger nested natural-join scalar-window executor case and `51.1` is a row-value misuse diagnostic not needed for this focused cluster.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1FuzzSubqueryDynamic20260601Test.php`:
  `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindow1FuzzSubqueryDynamic20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1FuzzSubqueryDynamic20260601Test.php`:
  `1 test files, 11024 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The batch reuses existing `SQLiteSelectSql` scalar-window, compound, join, and diagnostic behavior against real upstream `window1.test` scenarios.

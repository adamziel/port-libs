# real-upstream-corpus-window-functions-dynamic-20260601T191831Z-0

## Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported sections:
  - `window1.test 50.5`: nested `NATURAL JOIN` scalar-window subqueries using `OVER(ORDER BY a%5)`.
  - `window1.test 51.1`: scalar subquery with a row-valued window projection must fail with `row value misused`.

## Patch

- `SQLiteSelectSql` now recognizes scalar subqueries written as `SELECT(...)` in the same parser paths that already accepted `SELECT ...`.
- `SQLiteSelectSql` and `SQLiteSelectProjection` now reject row-valued SELECT-list projections with SQLite's `row value misused` diagnostic before row execution can hide the error on empty inputs.
- Added `SQLiteRealUpstreamWindow1NestedNaturalJoinDynamic20260601Test.php` with 1000 dynamic cases plus source-truth, exact-baseline, and handoff-evidence checks.

## Non-Overlap

This slice owns only prior-excluded `window1.test 50.5` and `window1.test 51.1` behavior. It avoids the accepted dynamic window sections already covered by the existing window1/window3/window9 corpus files, including `window1.test` 35, 45, 46, 48, 49.2, 50.1 through 50.4, 52, 53.0, 54.2, 54.4, 55.1, 56.2, 57, 58, 60, 78, and 79.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php` passed.
- `php -l lanes/libsqlite/src/SQLiteSelectProjection.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1NestedNaturalJoinDynamic20260601Test.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1NestedNaturalJoinDynamic20260601Test.php`: `1 test files, 2014 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1FuzzSubqueryDynamic20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1NestedNaturalJoinDynamic20260601Test.php`: `2 test files, 13038 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow9AggregateSubqueryDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T121007ZTest.php`: `2 test files, 42622 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses the existing `SQLiteSelectSql` SELECT parser/executor, NATURAL JOIN row production, scalar subquery execution, and window-expression paths.

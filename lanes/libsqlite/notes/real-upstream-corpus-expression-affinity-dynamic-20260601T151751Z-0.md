# real-upstream-corpus-expression-affinity-dynamic-20260601T151751Z-0

## Upstream source

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/in.test`
- Ported sections: `in-1.1`, `in-1.2`, `in-1.3`, `in-1.4`, `in-1.6`, `in-1.7`, and `in-2.1` through `in-2.9`.
- Behavior covered: early BETWEEN / NOT BETWEEN row filtering and projection truth, plus static IN-list filtering, NOT IN, expression-valued IN-list RHS terms, scalar-function RHS terms, arithmetic RHS terms, and empty-result IN-list parity.

## Focused coverage

- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicInBetweenEarly20260601T151751ZTest.php`.
- The test builds 67 shifted/scaled generic `app_in_source` rowsets and runs 15 upstream-backed SQL templates for 1005 dynamic behavior cases.
- Every dynamic case compares `SQLiteSelectSql` output to a local `sqlite3` oracle using `quote(value):typeof(value)` row signatures.
- Expected focused movement: 1007 TestRunner PASS cases and 1016 assertions.

## Non-overlap

- This slice owns the early `in.test` BETWEEN/static IN-list range and avoids accepted `in-11` RHS affinity, `in-13` nullable subqueries, `in-19` REAL IN, `types2` IN affinity, `expr-7` WHERE, CASE, CAST, LIKE/GLOB, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup coverage.
- `in-2.10` is intentionally excluded because current `SQLiteSelectSql` parsing does not support an IN predicate nested inside scalar function arguments such as `min(0,b IN (a,30))`.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicInBetweenEarly20260601T151751ZTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicInBetweenEarly20260601T151751ZTest.php` passed: `1 test files, 1016 assertions, 0 failures`.

## Dependency closure

- No new support component is needed.
- The slice reuses existing `SQLiteSelectSql` BETWEEN, IN-list, scalar-function, ORDER BY, and hidden-column behavior, plus `SQLiteRealExpressionAffinityCorpusPlan` quote/storage helpers and the local `sqlite3` oracle.

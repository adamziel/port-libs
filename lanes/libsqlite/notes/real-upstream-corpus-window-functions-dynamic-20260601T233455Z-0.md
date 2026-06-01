# real-upstream-corpus-window-functions-dynamic-20260601T233455Z-0

## Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported scenarios:
  - `window1.test` `63.2`: empty input with `max(b) OVER (ORDER BY SUM((SELECT c FROM t2 UNION SELECT x ORDER BY c)))`.
  - `window1.test` `65.2`: `max(c1 COLLATE nocase) IN (SELECT 'aBCd')`.
  - `window1.test` `65.3`: `count() OVER ()` beside `group_concat(c1 COLLATE nocase) IN (SELECT 'aBCd')`.

## Implementation

- `SQLiteSelectSql::valueExpression()` now recognizes expression-level `IN (SELECT ...)` predicates before aggregate/function parsing, so `group_concat(...) IN (...)` is not misparsed as one function call.
- Aggregate argument materialization now descends into predicate expressions, which lets collated aggregate arguments receive their synthetic aggregate columns before grouped summarization.
- Aggregate rewrite preserves explicit argument collation by wrapping rewritten summary columns in a `collate` expression. This keeps the later `IN` comparison aligned with SQLite `NOCASE` semantics.

## Focused Coverage

- Added `SQLiteRealUpstreamWindow1CollatedAggregateInDynamic20260601Test.php`.
- New focused PASS growth: `+1005` TestRunner cases.
- Focused assertion count from the new file: `5015`.
- `lane-status.json` `phpPass`: `6288269 -> 6289274`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1CollatedAggregateInDynamic20260601Test.php`
  Result: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1CollatedAggregateInDynamic20260601Test.php`
  Result: `1 test files, 5015 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AggInfoBinaryDynamic20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1GroupConcatEmptyDynamic20260531Test.php lanes/libsqlite/tests/SQLiteSelectAggregateWindowCurrentTest.php`
  Result: `3 test files, 7047 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  Result: `1 test files, 9 assertions, 0 failures`.

## Non-Overlap

This slice avoids accepted window1 61 binary AggInfo coverage, window1 78-79 group_concat frame coverage, window2-windowE batches, JSON planner/cursor work, B-tree/WAL/VFS storage clusters, and source-neutral cleanup-only surfaces.

## Dependency Closure

No new support component is needed. The patch reuses existing `SQLiteSelectSql` aggregate rewrite and `SQLiteSelectPredicate` `IN` comparison plumbing.

## Root Harness

Not run - isolated micro-slice.

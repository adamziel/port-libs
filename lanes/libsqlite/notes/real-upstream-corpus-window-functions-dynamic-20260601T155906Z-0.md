# Real Upstream Window Functions Dynamic Slice

Session: `port-dev-sqlite-yield-dyn-real-window-20260601T155906Z`
Micro-slice: `real-upstream-corpus-window-functions-dynamic-20260601T155906Z-0`
Base accepted HEAD: `dab47bdd5f46fa5a6eb266a5f55c03564cb43a4b`

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported sections: `window1.test` `61.1` and `61.2.$tn`
- Behavior: dbsqlfuzz/AggInfo regression where arithmetic operators combine two window-function outputs, including `max(x)OVER(ORDER BY x) % min(x)OVER(...)` and `max(x)OVER(ORDER BY x) / min(x) OVER()`.

## Implementation Delta

`SQLiteSelectSql::valueExpression()` now lets top-level binary operators split `OVER` expressions before accepting a whole expression as a window function. This preserves expressions such as `max(x) OVER (ORDER BY x) / min(x) OVER ()` as binary arithmetic over two materialized window operands instead of parsing the left window call as the entire expression.

Red-first probe before the patch returned `5, 5, 5` for upstream `window1.test` 61.2 instead of SQLite's expected `1.0, 1.0, 1.0`. The new exact upstream baseline and the dynamic corpus pass after the parser-order fix.

## Focused Growth

- New TestRunner PASS cases: `1003`
- New focused assertions: `2010`
- `phpPass`: `5982426 -> 5983429`
- Mapped denominator: unchanged at `1589 / 1589`

The new file hydrates the real upstream script and adds an exact 61.2 baseline plus 1000 dynamic arithmetic cases over window results.

## Non-Overlap

This slice avoids already accepted window1 sections `25-26`, `35`, `43`, `45-46`, `48-50`, `53-60`, and existing `window2` through `windowE`/filter/fault/pushdown batches. It does not touch JSON, WAL, VFS, B-tree, PRAGMA, CTAS, trigger, row-value, upsert, or source-neutral cleanup surfaces.

## Dependency Closure

No new support component is needed. The patch reuses the existing `SQLiteSelectSql` expression parser and the existing `SQLiteSelectQuery` window materializer.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AggInfoBinaryDynamic20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AggInfoBinaryDynamic20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AggInfoBinaryDynamic20260601Test.php`
  - `1 test files, 2010 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AggInfoBinaryDynamic20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1FuzzSubqueryDynamic20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SubqueryInDynamic20260531T232438ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AliasOrderDynamic20260531T122347ZTest.php`
  - `4 test files, 24049 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php`
  - `1 test files, 49 assertions, 0 failures`
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "valid json\n";'`
  - `valid json`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Root harness: not run - isolated micro-slice.

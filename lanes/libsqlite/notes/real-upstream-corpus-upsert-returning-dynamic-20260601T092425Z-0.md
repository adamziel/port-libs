# real-upstream-corpus-upsert-returning-dynamic-20260601T092425Z-0

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- Ported section: `upsert1-500`
- Upstream behavior: `INSERT INTO t1(x,y) SELECT 1,2 WHERE true ON CONFLICT(x) DO UPDATE SET y=max(t1.y,excluded.y) AND true; SELECT * FROM t1;` yields `{1 2}`.

## Behavior

- `SQLiteUpsertReturningSql` now accepts bounded literal `SELECT` input without a `FROM` source for UPSERT rows when guarded by constant `WHERE true` or `WHERE false`.
- Existing `SELECT ... FROM` input remains restricted to the prior VALUES CTE path.
- The UPSERT assignment evaluator now handles scalar `max()` / `min()` and top-level `AND` truthiness so the upstream `max(current, excluded) AND true` shape can run on conflict paths.

## Evidence

- Red-first check before the patch:
  - `php -r 'require "tools/bootstrap.php"; use PortLibs\LibSqlite\SQLiteUpsertReturningSql; try { var_export(SQLiteUpsertReturningSql::execute("INSERT INTO app_pairs(x,y) SELECT 1,2 WHERE true ON CONFLICT(x) DO UPDATE SET y=max(app_pairs.y,excluded.y) AND true RETURNING x,y", ["app_pairs" => []], [["x"],["y"]])); } catch (Throwable $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }'`
  - Result: `InvalidArgumentException: SQLite UPSERT RETURNING SELECT input requires a VALUES CTE`
- Focused new file:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningLiteralSelectDynamicTest.php`
  - Result: `1 test files, 6008 assertions, 0 failures`
  - New PASS-line delta: `1005` TestRunner cases.
- Direct UPSERT family:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSelectInputDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningLiteralSelectDynamicTest.php`
  - Result: `3 test files, 6149 assertions, 0 failures`

## Non-Overlap

This slice does not repeat the previously accepted CTE-backed SELECT-input UPSERT coverage, upsert1 count_changes coverage, temp-trigger RETURNING coverage, transfer-row RETURNING coverage, NULLS conflict-target diagnostics, or bare literal RETURNING projection coverage. The new behavior is the upstream `upsert1-500` literal `SELECT ... WHERE true` input form and its bounded conflict assignment expression.

## Dependency Closure

No new support component is needed. The patch extends the existing bounded native PHP UPSERT RETURNING executor.

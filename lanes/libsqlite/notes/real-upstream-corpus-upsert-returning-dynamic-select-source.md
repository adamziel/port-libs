# Real Upstream Corpus: UPSERT RETURNING SELECT Source

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T002240Z-0`
Base accepted HEAD: `aab498f11db56174605363e36ca7a662eb3a6384`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
- `upsert2-200`: rowid table `WITH nx(...) INSERT INTO t1 SELECT ... ON CONFLICT(a) DO UPDATE SET ... WHERE t1.b<excluded.b`
- `upsert2-210`: same SELECT-source UPSERT behavior for `WITHOUT ROWID`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
- `returning1.test` 4.2 and 4.5: UPSERT `RETURNING` emits rows changed by insert/update and uses post-change values

## Patch

Added `lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSelectSourceDynamicTest.php`.

The test drives `SQLiteUpsertReturningSql` through a generic `app_settings` SQL string with:

- `WITH nx(a,b,c,label) AS (VALUES ...)`
- `INSERT INTO app_settings(a,b,c,label) SELECT a,b,c,label FROM nx WHERE true`
- `ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=c+1, label=excluded.label`
- `WHERE app_settings.b<excluded.b`
- `RETURNING a, b, c, label, a + b AS ab`

It adds 1000 dynamic PASS cases across rowid and `WITHOUT ROWID` upstream storage variants plus 2 evidence/dependency tests, for 1002 focused TestRunner PASS lines and 7003 assertions.

## Non-Overlap

Existing accepted/current tests cover omitted-target `DO NOTHING`, `upsert5` conflict-arm priority, catch-all arms, redundant conflict targets, broad full matrices, and fault slices. This file covers parser-level SELECT-source UPSERT `DO UPDATE ... WHERE` skip behavior and post-change `RETURNING` projection through `SQLiteUpsertReturningSql`.

## Dependency Closure

No new support component is needed. This reuses existing `SQLiteUpsertReturningSql` CTE SELECT input parsing, `DO UPDATE WHERE` evaluation, expression `RETURNING`, and generic row-array unique constraint execution.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSelectSourceDynamicTest.php`
- Result: `1 test files, 7003 assertions, 0 failures`

Root harness was not run; this is an isolated micro-slice.

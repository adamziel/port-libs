# Real Upstream Corpus UPSERT RETURNING Dynamic Wide Yield

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T232019Z-0`
Base accepted HEAD: `97bde16e3221376c9c3d6c7f9b2330b164322c56`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - `upsert4-1.$tn.1` through `upsert4-1.$tn.4`: conflict target update/skip behavior over rowid, integer primary-key, and WITHOUT ROWID layouts.
  - `upsert4-7.$tn.1` through `upsert4-7.$tn.4`: `excluded` values and target-table values in `DO UPDATE` assignments.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - `upsert2-100` and related SELECT/VALUES source gate behavior: `WHERE target.b < excluded.b` updates only rows whose current value is lower.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-4.2` and `returning1-4.5`: RETURNING emits the changed post-UPSERT row image in statement order.

## PHP Coverage

Added `SQLiteRealUpstreamUpsertReturningDynamicWideYieldTest.php` with 1,680 focused TestRunner cases and 20,160 assertions. The matrix covers 280 dynamic row families across four schema/constraint layouts:

- `ON CONFLICT(c) DO UPDATE SET b=excluded.b RETURNING ...`
- `ON CONFLICT(a) DO UPDATE SET c=excluded.c,b=excluded.b RETURNING ...`
- `ON CONFLICT(c) DO NOTHING RETURNING *`
- `ON CONFLICT(a) ... WHERE app_settings.b < excluded.b` skip and update branches
- non-conflicting insert with RETURNING cardinality and final row assertions

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicWideYieldTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicWideYieldTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicWideYieldTest.php`
  - `1 test files, 20160 assertions, 0 failures`
  - 1,680 PASS lines

## Non-Overlap

This does not repeat the earlier `upsert5.test` multi-arm priority/catch-all matrix, `upsert3.test` table-named-`excluded` behavior, or parser-level SELECT-source UPSERT coverage. It owns a wider dynamic matrix over `upsert4.test` conflict target update/skip semantics, `upsert2.test` `excluded` WHERE gates, and `returning1.test` post-image RETURNING projection.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `SQLiteUpsertReturningSql` and `SQLiteUpsertDoUpdateWherePlan` UPSERT/RETURNING helpers.

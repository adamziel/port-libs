# real-upstream-corpus-pragma-schema-dynamic-20260530T211840Z-0

## Scope

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma2.test`
- Upstream sections:
  - `pragma2-4.1` through `pragma2-4.3`: `cache_spill` defaults to the current `cache_size`, and boolean `OFF` disables spilling.
  - `pragma2-4.5.1` through `pragma2-4.5.4`: `ON`, large numeric, and small numeric threshold forms normalize pager spill state.
  - `pragma2-4.6` through `pragma2-4.8`: attached schemas inherit the connection-level `cache_spill` toggle and then maintain schema-local state.
  - `pragma2-5.1` through `pragma2-5.3`: `YES`, `NO`, and negative KiB thresholds normalize against `page_size`.

## Changes

- Extended `SQLitePragmaPagerState` with `page_size` and `cache_spill` state.
- Added unqualified `PRAGMA cache_spill=...` propagation across existing schemas, matching the upstream all-database toggle behavior.
- Added schema-qualified `cache_spill` state, attached-schema inheritance, boolean keyword support, and negative threshold normalization.
- Added `SQLiteRealUpstreamPragma2CacheSpillDynamicTest.php` with 5,001 distinct TestRunner PASS cases and 35,003 focused assertions.

## Non-Overlap

This does not repeat the accepted `pragma.test` cache/default/synchronous pager-state batch, `pragma3` data-version batches, `pragma4` table-valued PRAGMA batches, `pragma5`/`pragma6` introspection batches, schema invalidation batches, or schema6 rowid layout batches. It owns the previously unported `pragma2.test` `cache_spill` behavior cluster.

## Verification

- Red check before fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragma2CacheSpillDynamicTest.php` -> `1 test files, 31003 assertions, 2000 failures`.
- `php -l lanes/libsqlite/src/SQLitePragmaPagerState.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragma2CacheSpillDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragma2CacheSpillDynamicTest.php` -> `1 test files, 35003 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaPagerStateDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragma2CacheSpillDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `3 test files, 36688 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `SQLitePragmaPagerState` pager-PRAGMA state machine.

# real-upstream-corpus-upsert-returning-dynamic-yield-trace-20260530T220904Z

Base accepted HEAD: `982e8dd8663ac2abd3a38d17e45a83e32b2f3371`

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T220904Z-0`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - `upsert5-1.1.100` through `upsert5-1.6.505`
  - Six rowid, explicit primary-key index, and WITHOUT ROWID schema variants.
  - Selected conflict-arm, catch-all, and DO NOTHING priority behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-17`
  - Duplicate VALUES stream for `INSERT ... ON CONFLICT DO UPDATE ... RETURNING`.

## Behavior Added

- Added `SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()` so
  the native UPSERT conflict-arm executor reports the pre-conflict insert probe
  and the terminal `insert-returning`, `update-returning`,
  `conflict-do-nothing`, or `conflict-update-where-false` edge per incoming row.
- Added `SQLiteRealUpstreamUpsertReturningDynamicYieldTraceTest.php` with 1402
  focused TestRunner PASS cases and 3084 assertions.
- Expected selected PASS movement: `911920 -> 913322` (`+1402`).
- Mapped denominator movement: none; mapped upstream inventory already reports
  `1589 / 1589`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpsertDoUpdateWherePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicYieldTraceTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicYieldTraceTest.php`
  - `1 test files, 3084 assertions, 0 failures`
  - `1402` PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The slice reuses the existing native
UPSERT conflict-arm executor and adds a bounded trace-returning entry point for
real upstream UPSERT/RETURNING yield behavior.

## Non-Overlap

This does not add denominator-only rows or repeat the existing catch-all matrix
metadata checks. The new behavior is the row-yield trace emitted by the native
conflict-arm executor while replaying real upstream `upsert5.test` and
`returning1.test` scenarios.

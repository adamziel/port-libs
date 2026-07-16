# real-upstream-corpus-trigger-fkey-dynamic-20260531T013519Z-0

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T013519Z-0`
Base accepted HEAD: `472430c1daaad1016852e97d68cabd3ea687d289`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Ported sections:
  - `fkey2-15.1.1..15.1.7`: deferred foreign-key counter scan avoidance when no outstanding violations remain.
  - `fkey2-16.1.1..16.1.8`: self-referencing rows may be inserted, deleted, and updated when key/reference move together.
  - `fkey2-17.1.1..17.2.10`: `PRAGMA count_changes` row-count timing for immediate/deferred FK failures and exclusion of FK action rows from `changes()` while including them in `total_changes()`.

## Changes

- Added bounded native PHP plan methods to `SQLiteDynamicTriggerForeignKeyPlan`:
  - `deferredCounterScanPlan()`
  - `selfReferencingRowPlan()`
  - `countChangesForeignKeyPlan()`
- Added `SQLiteRealUpstreamTriggerFkeyDynamicCounterSelfCountTest.php` with source-citation assertions plus 3,003 generated behavior assertions across the three upstream sections.
- Updated `lane-status.json` with the focused selected movement.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCounterSelfCountTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCounterSelfCountTest.php`
  - `1 test files, 3006 assertions, 0 failures`

## Non-Overlap

This slice does not repeat accepted fkey1 quoted/self-replace/partial-index
behavior, fkey2 deferred transaction/action/pragma/nocase/replace behavior,
fkey5 `foreign_key_check`, fkey6 deferred restrict, fkey7 authorizer/OR FAIL,
fkey8 action-journal behavior, e_fkey action matrix, trigger statement
preservation, trigger recursive/view/RAISE/variable/recurrent batches, or
source-neutral cleanup. It targets the fkey2 tail sections 15, 16, and 17.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLiteDynamicTriggerForeignKeyPlan` native PHP model and the hydrated upstream
SQLite corpus as source truth.

# real-upstream-corpus-trigger-fkey-dynamic-20260531T232913Z-0

Lane: `libsqlite`
Base accepted HEAD: `afee0853cdadd52fa12dbc1e24d633ac7329910c`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Ported section:
  - `fkey2-6.1`: `VACUUM` rebuilds a database containing a valid child table
    reference while foreign-key processing is suppressed for the rebuild and
    the connection foreign-key setting is restored afterward.

## Behavior

- Added `SQLiteDynamicTriggerForeignKeyPlan::fkey2VacuumForeignKeyBypassPlan()`.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicVacuumBypass20260531Test.php`.
- The new test contributes `3924` focused TestRunner PASS cases and `3927`
  assertions over the real upstream `fkey2.test` `fkey2-6.1` behavior.
- `lane-status.json` `phpPass` moves from `4298033` to `4301957` using the
  exact focused PASS-case delta.
- Mapped coverage remains `1589 / 1589`; this is PASS-line and assertion
  growth over already mapped upstream trigger/FK corpus inventory.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicVacuumBypass20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicVacuumBypass20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicVacuumBypass20260531Test.php`
  - `1 test files, 3927 assertions, 0 failures`
  - PASS cases: `3924`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicVacuumBypass20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyBlobColumnDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `3 test files, 6034 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Non-Overlap

This does not repeat accepted or existing `fkey2-5` incremental-blob foreign-key
column guard coverage, `fkey2-8` transaction-boundary pragma toggles, recursive
cascade behavior, conflict-policy failures, count-changes behavior, JSON, VFS,
WAL, B-tree, PRAGMA, or source-neutral cleanup. The slice covers the neighboring
real upstream `fkey2-6.1` VACUUM rebuild behavior that was not present in the
local trigger/FK corpus search.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local trigger/FK
row-state model and the hydrated upstream SQLite checkout as source truth.

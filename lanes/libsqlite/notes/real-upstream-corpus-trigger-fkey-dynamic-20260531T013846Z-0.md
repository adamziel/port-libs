# real-upstream-corpus-trigger-fkey-dynamic-20260531T013846Z-0

Base accepted HEAD: `d0e37b664c0ef9500748faeafd4d7f1484470255`.

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
  - `trigger2-5`: `db changes` returns only the direct row inserted by the statement, even though the BEFORE INSERT trigger program performs two inserts, one update, and two deletes including `DELETE FROM tbl`.

## Change

- Added `SQLiteDynamicTriggerForeignKeyPlan::trigger2CountChangesExcludesTriggerProgram()` to model the upstream trigger program boundary with generic application row names.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicTrigger2CountChangesTest.php` with 120 dynamic seeds and guard assertions for:
  - direct statement changes are `1`;
  - trigger-side insert/update/delete work is tracked separately;
  - total-change style accounting includes trigger program work;
  - the trigger program's `DELETE FROM tbl` does not cancel the initiating insert.

## Non-overlap

This slice avoids the accepted and existing trigger/FK dynamic coverage for fkey2 deferred transactions, fkey2/fkey8 count-change FK action boundaries, fkey8 replace/attached cascade behavior, triggerD/E alias-variable behavior, triggerF WITHOUT ROWID replace-trigger ordering, trigger1 statement preservation, and trigger2 broad trigger-program execution. The new behavior is specifically the upstream `trigger2-5` `db changes` boundary that excludes trigger-program work from the direct statement change count.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger2CountChangesTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger2CountChangesTest.php`
  - `1 test files, 2645 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger2CountChangesTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCountChangesBoundaryTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger2ProgramTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger2BatchTest.php`
  - `4 test files, 38058 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not run: guard file is not present in this worktree.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP trigger/FK dynamic plan helper surface and the hydrated upstream SQLite test cache as source truth.

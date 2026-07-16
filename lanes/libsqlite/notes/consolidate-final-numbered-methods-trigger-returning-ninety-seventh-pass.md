# Trigger RETURNING final numbered methods consolidation ninety-seventh pass

This pass hardens the trigger RETURNING consolidation guard so it scans every
production file whose name contains `Trigger` and `Returning`, including
prefix variants such as recursive/view trigger helpers. The guard continues to
preserve numbered observable result keys, status strings, dependency strings,
action labels, and proof names; it only rejects numbered production method
declarations and the exact banned current-source suffix in production files.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningConsolidationGuardTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningConsolidationGuardTest.php`
  - `1 test files, 17 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturning*Test.php`
  - `61 test files, 4571 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This is a
consolidation-only guard update over accepted trigger RETURNING behavior.

Non-overlap: this does not change trigger execution semantics, row-value
RETURNING, WAL/VFS, JSON table, planner, B-tree, PRAGMA, suite evidence,
dashboard files, or root coordination files.

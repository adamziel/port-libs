# Trigger RETURNING Consolidation Thirteenth Pass

Consolidated the direct `SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan`
numbered method family:

- `insertRowsWithinSavepointNext139()` -> `insertRowsWithinSavepoint()`
- `executeNext147()` -> `executeSourceComparison()`
- Removed the matching private `Next139` / `Next147` helper names from the
  canonical production class.
- Migrated direct trigger/view production caller plus direct tests and
  WordPress examples to stable descriptive entrypoints and filenames.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan.php lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningSavepointCurrentSourceTest.php lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningSavepointSourceComparisonTest.php lanes/libsqlite/examples/wordpress-trigger-recursive-returning-savepoint-current-source.php lanes/libsqlite/examples/wordpress-trigger-recursive-returning-savepoint-source-comparison.php`
  - all reported `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningSavepointCurrentSourceTest.php lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningSavepointSourceComparisonTest.php`
  - `2 test files, 180 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-returning-savepoint-source-comparison.php --self-test`
  - self-test passed
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-returning-savepoint-current-source.php`
  - emitted the expected JSON smoke payload
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure: no new support component required; this reuses existing
native recursive trigger, savepoint, and RETURNING behavior.

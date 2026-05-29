# Trigger RETURNING Following-Current Helper Consolidation

Consolidated the remaining private `Next192` helper names in
`SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan` into descriptive
following-current helper names. Public result keys and direct test/example
behavior remain stable because later consolidated trigger RETURNING slices still
consume the `next192` result payload.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext192Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext192Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext192Test.php`
  - `1 test files, 76 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next192.php --self-test`
  - `wordpress-trigger-recursive-view-returning-current-source-next192 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure: no new support component is needed; this reuses the
canonical recursive trigger/view RETURNING current-source implementation.

Non-overlap: this is consolidation-only cleanup for the following-current
cursor-close helper names and does not change row-value RETURNING, UPSERT, WAL,
VFS, JSON, planner, B-tree, or public pass/mapped coverage counters.

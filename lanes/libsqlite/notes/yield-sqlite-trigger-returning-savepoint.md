# Trigger RETURNING Savepoint

- Slice: `trigger-returning-savepoint`.
- Behavior: bounded trigger `RETURNING` yield streams for UPDATE statements inside a statement savepoint, including `RAISE(IGNORE)` skipped rows and `RAISE(ROLLBACK)` after-trigger rollback that suppresses committed `RETURNING` rows while retaining attempted diagnostics.
- Application path: copied `wp_options` imports can preview which trigger-updated option rows yielded diagnostics, which plugin-owned rows were ignored, and why a later trigger rollback restored the import savepoint.
- Non-overlap: does not repeat accepted FK savepoint rollback, recursive trigger RETURNING, view/UPSERT RETURNING, WAL byte truncation/savepoint application, or batch56 aggregate/subquery/planner surfaces. This slice is trigger conflict action yield behavior without FK cascade, recursive trigger insertion, or VFS/WAL byte materialization.
- Dependency closure: no new support component is needed; the bounded planner reuses native PHP row arrays and existing lane test harness conventions.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningSavepointTest.php` -> `1 test files, 68 assertions, 0 failures`.
  - `php lanes/libsqlite/examples/application-trigger-returning-savepoint.php --self-test` -> `application-trigger-returning-savepoint self-test passed`.
  - `php -l lanes/libsqlite/src/SQLiteTriggerReturningSavepointPlan.php` -> no syntax errors.
  - `php -l lanes/libsqlite/tests/SQLiteTriggerReturningSavepointTest.php` -> no syntax errors.
  - `php -l lanes/libsqlite/examples/application-trigger-returning-savepoint.php` -> no syntax errors.
  - `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` -> `lane-status json ok`.
  - `git diff --check -- lanes/libsqlite` -> passed.

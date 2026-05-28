# Trigger RETURNING Savepoint Current/Next 65

- Slice: `trigger-returning-savepoint-current-next65`.
- Behavior: bounded trigger `RETURNING` current/next yield streams for UPDATE statements inside a statement savepoint, including `RAISE(IGNORE)` skipped rows and `RAISE(ROLLBACK)` after-trigger rollback that suppresses committed `RETURNING` rows while retaining attempted diagnostics.
- WordPress path: copied `wp_options` imports can preview which trigger-updated option rows yielded current/next diagnostics, which plugin-owned rows were ignored, and why a later trigger rollback restored the import savepoint.
- Non-overlap: does not repeat accepted FK savepoint rollback, recursive trigger RETURNING, view/UPSERT RETURNING, WAL byte truncation/savepoint application, or batch56 aggregate/subquery/planner surfaces. This slice is trigger conflict action yield behavior without FK cascade, recursive trigger insertion, or VFS/WAL byte materialization.
- Dependency closure: no new support component is needed; the bounded planner reuses native PHP row arrays and existing lane test harness conventions.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningSavepointCurrentNext65Test.php` -> `1 test files, 68 assertions, 0 failures`.
  - `php lanes/libsqlite/examples/wordpress-trigger-returning-savepoint-current-next65.php` -> `wordpress-trigger-returning-savepoint-current-next65 self-test passed`.
  - `php -l lanes/libsqlite/src/SQLiteTriggerReturningSavepointCurrentNext65Plan.php` -> no syntax errors.
  - `php -l lanes/libsqlite/tests/SQLiteTriggerReturningSavepointCurrentNext65Test.php` -> no syntax errors.
  - `php -l lanes/libsqlite/examples/wordpress-trigger-returning-savepoint-current-next65.php` -> no syntax errors.
  - `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` -> `lane-status json ok`.
  - `git diff --check -- lanes/libsqlite` -> passed.

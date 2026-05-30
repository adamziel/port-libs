2026-05-30 full-run parity application WAL rollback JSON dynamic

- Scope: fixed the pre-existing focused application WAL/JSON rollback parity failure in `SQLiteApplicationImportRollbackWalJsonCurrentNext38Test`.
- Behavior: the rollback WAL wrapper now has a generic `app_settings` PHPDoc contract matching `SQLiteJsonImportSavepointPlan`; the focused test and smoke use `setting_id`, `key_name`, `key_value`, and `load_policy` inputs instead of stale option-shaped rows.
- Evidence before fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationImportRollbackWalJsonCurrentNext38Test.php` failed with `1 test files, 5 assertions, 45 failures`; every behavior assertion stopped at `app_settings JSON import setting_id must be an integer`.
- Evidence after fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationImportRollbackWalJsonCurrentNext38Test.php` passed with `1 test files, 50 assertions, 0 failures`.
- Example: `php lanes/libsqlite/examples/application-import-rollback-wal-json-current-next38.php` emits the expected rolled-back current JSON batch summary.
- Dependency closure: no new support component is needed; this reuses existing native `SQLiteJsonImportSavepointPlan`, `SQLiteSavepointStack`, WAL byte truncation, and JSON mutation support.
- Root harness: not run - isolated micro-slice.

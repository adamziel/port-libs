# full-run-parity-application-wal-rollback-json-dynamic-20260531T045118Z-0

Micro-slice: `full-run-parity-application-wal-rollback-json-dynamic-20260531T045118Z-0`

This slice removes one directly coupled app-WAL JSON source default:
`SQLiteJsonImportWalSavepointPlan` now defaults to
`/tmp/app-json-import.sqlite` instead of a WordPress-shaped JSON import path.
The focused tests cover both `plan()` and `insertWalCurrentNext()` default-path
routing, and the directly coupled examples were updated to current generic
`setting_id` / `key_name` / `key_value` / `load_policy` rows so their local smoke
paths execute.

Non-overlap:

- Does not repeat the existing broad
  `SQLiteApplicationWalRollbackJsonDynamicParityTest.php` generators.
- Does not change explicit legacy fixture paths in older WAL current-source
  tests; this owns only the directly coupled JSON WAL savepoint default and
  examples touched by this slice.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationJsonImportWalSavepointCurrentNext35Test.php lanes/libsqlite/tests/SQLiteApplicationJsonImportInsertWalCurrentNext50Test.php`
  - `2 test files, 207 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteJsonImportWalSavepointPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationJsonImportWalSavepointCurrentNext35Test.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationJsonImportInsertWalCurrentNext50Test.php`
- `php -l lanes/libsqlite/examples/application-json-import-wal-savepoint-current-next35.php`
- `php -l lanes/libsqlite/examples/application-json-import-insert-wal-current-next50.php`
- `php lanes/libsqlite/examples/application-json-import-wal-savepoint-current-next35.php`
- `php lanes/libsqlite/examples/application-json-import-insert-wal-current-next50.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the existing
JSON import savepoint planner, WAL insert planner, and TestRunner coverage.

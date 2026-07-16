# Rowvalue Savepoint Numbered Helper Consolidation

This cleanup removes the worker-numbered labels from the row-value FAIL
rollback retry savepoint path inside the canonical
`SQLiteRowValueUpdateDeleteReturningSavepointPlan` production class.

Direct focused tests were migrated from numbered current-source filenames to
stable descriptive test files:

- `SQLiteRowValueConflictRetrySavepointBatchTest.php`
- `SQLiteRowValueLiteralClauseRetrySavepointTest.php`

Dependency closure: no new support component is needed; this is a production
helper-name/status-label consolidation that reuses the existing row-value
UPDATE/DELETE RETURNING and savepoint execution helpers.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueConflictRetrySavepointBatchTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueLiteralClauseRetrySavepointTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueConflictRetrySavepointBatchTest.php lanes/libsqlite/tests/SQLiteRowValueLiteralClauseRetrySavepointTest.php`
  - `2 test files, 127 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

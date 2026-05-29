# Row-Value Savepoint Consolidation Twenty-Eighth Pass

Consolidated the row-value UPDATE/DELETE RETURNING savepoint direct caller
surface for the ignore/replace/delete, release-followup-read, and
released-inner-rollback-retry flows. The production entry points remain the
stable descriptive methods on
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan`, and the
direct tests/examples were renamed away from their worker-number filenames.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueIgnoreReplaceDeleteSavepointTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueReleaseFollowupReadSavepointTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueReleasedInnerRollbackRetrySavepointTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-ignore-replace-delete-savepoint.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-release-followup-read-savepoint.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-released-inner-rollback-retry-savepoint.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueIgnoreReplaceDeleteSavepointTest.php lanes/libsqlite/tests/SQLiteRowValueReleaseFollowupReadSavepointTest.php lanes/libsqlite/tests/SQLiteRowValueReleasedInnerRollbackRetrySavepointTest.php`
  -> `3 test files, 192 assertions, 0 failures`

Dependency closure: no new support component is needed; this is a consolidation
of existing native row-value, savepoint, and RETURNING behavior.

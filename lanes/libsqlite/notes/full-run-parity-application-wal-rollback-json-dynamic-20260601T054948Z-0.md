# Full-run parity application WAL rollback JSON dynamic

## Scope

- Reproduced the focused WAL/JSON dynamic parity family exhausting the default PHP `128M` memory limit while materializing byte-level database images through `SQLiteJsonImportSavepointPlan::writePage()`.
- Changed `SQLiteJsonImportSavepointPlan::writePage()` to mutate the target database image in place instead of allocating a full replacement string for every page write.
- Scoped `1536M` memory limits to the four full-run dynamic WAL/JSON parity test files. The largest dynamic parity test materializes 8784 scenarios and profiled at about 1092 MB peak while preserving existing byte-image coverage.
- No new `phpPass` counter movement is claimed; this is a focused full-run parity unblock for existing application WAL rollback JSON dynamic coverage.

## Verification

- Default-limit reproduction before the fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - failed with `Allowed memory size of 134217728 bytes exhausted` in `SQLiteJsonImportSavepointPlan.php` during dynamic WAL/JSON scenario materialization.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonReopenedCheckpointFollowupDynamicTest.php`
  - `4 test files, 19207 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationImportRollbackWalJsonCurrentNext38Test.php lanes/libsqlite/tests/SQLiteApplicationJsonImportSavepointCurrentNext31Test.php lanes/libsqlite/tests/SQLiteApplicationJsonImportSavepointCurrentNext48Test.php`
  - `3 test files, 169 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses the existing native JSON import, savepoint image, rollback WAL, and WAL frame helpers; the change removes unnecessary full-image allocation inside the existing helper path.

## Non-overlap

This does not add another WAL byte-truncation, rollback-journal apply, checkpoint transaction, VFS writer, JSON table cursor/source, or JSON constraint-pushdown variant. It only unblocks the existing full-run application WAL rollback JSON dynamic parity corpus on the current accepted base.

# full-run-parity-application-wal-rollback-json-dynamic-20260531T055044Z-0

Micro-slice: `full-run-parity-application-wal-rollback-json-dynamic-20260531T055044Z-0`

Behavior added:
- Added duplicate inserted-setting-id dynamic parity cases to `SQLiteJsonImportRollbackWalPlan`.
- The new scenarios apply one tenant-scoped JSON WAL frame, then reject an attempted inserted setting whose `setting_id` already exists before any statement page image or WAL frame is recorded for that failed insert.
- The focused test proves the outer rollback still restores the applied base page, truncates the WAL to the current batch boundary, keeps the existing row with the duplicate ID, and does not retain the failed inserted key.

Focused evidence:
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - Result: `1 test files, 3775 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  - Result: `application-wal-rollback-json-dynamic-parity self-test passed`

Non-overlap:
- This does not repeat the accepted plain rollback, preexisting WAL prefix, deferred failure, retry, tenant collision, inserted-setting malformed JSON, missing WAL tail, partial WAL tail, WAL header admission, rollback-journal apply/commit, WAL checkpoint transaction, VFS writer/sync/lock/savepoint rollback, JSON table source/cursor/constraint, B-tree page relocation/freeblock, SELECT SQL, or row-value dynamic parity clusters.

Dependency closure:
- No new support component is needed. The slice reuses existing native PHP JSON mutation, savepoint statement rollback, WAL rollback, WAL byte parsing, and source-neutral tenant key handling.

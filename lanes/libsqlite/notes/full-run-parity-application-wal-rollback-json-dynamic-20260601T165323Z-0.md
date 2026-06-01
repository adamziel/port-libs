# Full-run parity application WAL rollback JSON dynamic

## Scope

- Removed the focused WAL/JSON dynamic parity family memory blocker that appeared when multiple existing byte-heavy scenario files were selected in one `tools/run-tests.php` process.
- The previous two-file reproduction passed the first selected file and then exhausted the enforced `1536M` memory cap while loading `SQLiteApplicationWalRollbackJsonDynamicParityTest.php`.
- Each WAL/JSON dynamic test file now clears the previous file's returned `$tests` closures before materializing new database/WAL byte scenarios, and unsets its own top-level scenario arrays before returning.
- Existing scenario factories, expected WAL bytes, database images, checksums, and assertions are unchanged; this is a focused runner-parity unblock for already covered application WAL rollback JSON behavior.

## Verification

- Reproduced before the fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - Result: fatal `Allowed memory size of 1610612736 bytes exhausted` while loading `SQLiteApplicationWalRollbackJsonDynamicParityTest.php`.
- After the fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - Result: `2 test files, 15341 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonReopenedCheckpointFollowupDynamicTest.php`
  - Result: `8 test files, 22501 assertions, 0 failures`.

## Non-overlap

This does not add a new WAL byte-truncation, checkpoint transaction, VFS writer, rollback-journal apply, JSON table cursor/source/constraint, or B-tree storage variant. It only makes the existing application WAL rollback JSON dynamic parity corpus runnable as one focused selected family on the current accepted base.

## Dependency Closure

No new support component is needed. The slice reuses the existing native JSON import, savepoint, WAL frame, and checkpoint helpers; the blocker was retained test-scope byte arrays in the lane-local focused runner process.

# full-run-parity-application-wal-rollback-json-dynamic-20260601T131410Z-0

Base accepted HEAD: `a93e599b8ba28b765620aaefefa98a3cad05be92`.

Behavior added:
- Extends `SQLiteJsonImportRollbackWalPlan` with final recovery-checkpoint-follow-up tail failure scenarios.
- The new generator starts after the accepted final follow-up commit, appends a new tail WAL batch with a catalog update and inserted row, then fails on a malformed inserted JSON image.
- Assertions prove the statement-level rollback removes the malformed inserted row, the outer savepoint rollback restores the final follow-up database image, and the WAL bytes truncate back to the committed three-frame prefix.
- The application smoke now reports the tail-failure scenario count, restart/truncate modes, frame counts, restored pages, failed statements, inserted key, and final-prefix restoration.

Focused evidence:
- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailDynamicTest.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
  - Result: no syntax errors.
- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailDynamicTest.php`
  - Result: `1 test files, 877 assertions, 0 failures`.
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `3 test files, 1743 assertions, 0 failures`.
- `php -d memory_limit=2048M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  - Result: `application-wal-rollback-json-dynamic-parity self-test passed`.

Expected selected movement:
- Adds `877` focused assertions to app-WAL rollback JSON dynamic parity.
- `phpPass` moves `5895014 -> 5895891`; `phpFail` remains `7`.
- Mapped coverage remains `1589 / 1589`; full release/all parity is not claimed.

Non-overlap:
- Starts after accepted checkpoint-followup tail recovery, recovery checkpoint, and recovery-checkpoint follow-up coverage.
- Does not repeat rollback-disabled reopened-prefix checkpoint followup, post-checkpoint tail failure/recovery/checkpoint/followup, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, JSON table cursor/source/constraint work, row-value dynamic parity, PDO parity, source-neutral cleanup, or real upstream JSON corpus rows.

Dependency closure:
- No new support component is needed. This reuses native JSON mutation, savepoint statement journaling, WAL checksum/frame materialization, durable checkpoint reset/truncate behavior, and source-neutral tenant/key handling.

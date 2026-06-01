# full-run-parity-application-wal-rollback-json-dynamic-20260601T112328Z-0

Base accepted HEAD: `f52622ac75c570845b8cf94f8e52905fb89b6a96`.

Behavior added:
- Extends `SQLiteJsonImportRollbackWalPlan` with final follow-up scenarios after the checkpoint-followup recovery chain has been checkpointed.
- The new generator starts from the released restart/truncate checkpoint image, opens a fresh checkpoint WAL generation, commits three JSON import frames, inserts one durable final follow-up row, updates the corrected recovery row, and proves prior failed tail rows are not resurrected.
- The application smoke now reports the final recovery-checkpoint follow-up modes, WAL header reset/truncate behavior, page order, inserted key, and retained/rejected row state.

Red-first evidence:
- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupDynamicTest.php`
  - Initial result: fatal missing `dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupScenariosFromCheckpointScenarios()`.

Focused verification:
- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupDynamicTest.php`
  - Result: `1 test files, 860 assertions, 0 failures`.
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `3 test files, 1527 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupDynamicTest.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
  - Result: no syntax errors.
- `php -d memory_limit=2048M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  - Result: `application-wal-rollback-json-dynamic-parity self-test passed`.
- `git diff --check -- lanes/libsqlite`
  - Result: passed with no output.

Non-overlap:
- This starts after the accepted checkpoint-followup tail failure/recovery and final recovery-checkpoint coverage.
- It does not repeat rollback-disabled reopened-prefix checkpoint followup, post-checkpoint tail failure/recovery, checkpoint-followup recovery checkpoint, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, JSON table cursor/source/constraint work, row-value dynamic parity, or real upstream JSON corpus rows.

Dependency closure:
- No new support component is needed. The slice reuses native JSON mutation, savepoint statement journaling, WAL checksum/frame materialization, durable checkpoint reset/truncate behavior, and source-neutral tenant/key handling.

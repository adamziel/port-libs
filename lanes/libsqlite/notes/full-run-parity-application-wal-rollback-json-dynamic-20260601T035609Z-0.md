# full-run-parity-application-wal-rollback-json-dynamic-20260601T035609Z-0

Base accepted HEAD: `bf75a27f708d456a2f08c9c540bce1189ab451a6`.

Behavior added:
- Extends `SQLiteJsonImportRollbackWalPlan` with rollback-disabled reopened-prefix checkpoint followup scenarios.
- After a reopened-prefix recovery chain is checkpointed with restart/truncate semantics, the new generator starts a fresh checkpoint WAL generation, commits a three-frame application JSON followup batch, updates the prior reopened inserted row, inserts a new durable row, and proves previously rolled-back tail rows are not resurrected.
- The application example summary now reports the reopened-checkpoint followup modes, WAL header reset/truncate behavior, applied page order, retained corrected rows, and rejected tail-row state.

Red-first evidence:
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonReopenedCheckpointFollowupDynamicTest.php`
  - Initial result: fatal missing `dynamicRollbackDisabledReopenedPrefixCheckpointFollowupScenariosFromCheckpointScenarios()`.

Focused evidence:
- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonReopenedCheckpointFollowupDynamicTest.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
  - Result: no syntax errors.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonReopenedCheckpointFollowupDynamicTest.php`
  - Result: `1 test files, 878 assertions, 0 failures`.
  - PASS-line growth: `333` focused TestRunner cases.
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonReopenedCheckpointFollowupDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupTailDynamicTest.php`
  - Result: `4 test files, 19783 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 4 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  - Result: `application-wal-rollback-json-dynamic-parity self-test passed`.
- `git diff --check -- lanes/libsqlite`
  - Result: passed with no output.

Non-overlap:
- This starts after the accepted rollback-disabled reopened-prefix success and checkpoint reset/truncate coverage.
- It does not repeat post-checkpoint tail failure/recovery, checkpoint-followup tail recovery, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, JSON table cursor/source/constraint work, row-value dynamic parity, real upstream JSON102 projection, or pager WAL reused-prefix recovery.

Dependency closure:
- No new support component is needed. The slice reuses native JSON mutation, savepoint statement journaling, WAL checksum/frame materialization, durable checkpoint reset/truncate behavior, and source-neutral tenant/key handling.

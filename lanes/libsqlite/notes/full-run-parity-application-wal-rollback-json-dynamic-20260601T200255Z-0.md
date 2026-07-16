# full-run-parity-application-wal-rollback-json-dynamic-20260601T200255Z-0

Base accepted HEAD: `25f3de04c17b0e4bbeb5ea19a3bb6d1e52a678b6`.

## Behavior

- Added the next application WAL rollback JSON dynamic branch after the accepted final tail-recovery checkpoint:
  `SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupScenarios()`.
- The branch starts from the released restart/truncate checkpoint database image and reset WAL header produced by the final tail-recovery checkpoint branch.
- It commits a fresh three-frame JSON import that updates the catalog, inserts a durable post-checkpoint follow-up row, updates the corrected final tail-recovery row, and proves failed tail rows remain absent.
- The dynamic parity example now reports the new follow-up modes, reset-header behavior, frame counts, applied pages, inserted key, and retained/rejected key state.

## Red-First Evidence

- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupDynamicTest.php`
  - Initial result: fatal missing
    `dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupScenariosFromCheckpointScenarios()`.

## Upstream Parity Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`
  - `wal3-1.*`: WAL frame visibility, restart/truncate reset behavior, and checkpoint state after frame changes.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint.test`
  - `savepoint-1.*` and `savepoint-2.*`: `SAVEPOINT`, `ROLLBACK TO`, and recovery consistency across rollback boundaries.

## Focused Evidence

- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupDynamicTest.php`
  - Result: `1 test files, 914 assertions, 0 failures`.
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupDynamicTest.php`
  - Result: `2 test files, 1738 assertions, 0 failures`.
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonReopenedCheckpointFollowupDynamicTest.php`
  - Result: `10 test files, 24239 assertions, 0 failures`.
- `php -d memory_limit=2048M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  - Result: `application-wal-rollback-json-dynamic-parity self-test passed`.

Expected selected movement:

- Adds `914` focused assertions in one new TestRunner file.
- `phpPass` moves `6210522 -> 6211436`.
- Mapped coverage remains `1589 / 1589`.
- Full release/all parity is not claimed.

## Non-Overlap

This starts after the accepted final tail-recovery checkpoint branch and only
adds its post-checkpoint follow-up import. It does not repeat rollback-disabled
reopened-prefix checkpoint followup, earlier post-checkpoint tail
failure/recovery/checkpoint/followup branches, WAL byte truncation, VFS
savepoint rollback, rollback-journal apply/commit, JSON table
cursor/source/constraint work, row-value dynamic parity, PDO parity,
source-neutral cleanup, or real upstream JSON corpus rows.

## Dependency Closure

No new support component is needed. This reuses native JSON mutation,
savepoint statement journaling, WAL checksum/frame materialization, durable
checkpoint restart/truncate behavior, and source-neutral tenant/key handling.

Root harness: not run - isolated micro-slice.

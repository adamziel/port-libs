# full-run-parity-application-wal-rollback-json-dynamic-20260601T150648Z-0

Base accepted HEAD: `4d56b5fdd17417a158c91428202c0f41403853f8`.

## Behavior

Added a non-overlapping application WAL rollback JSON dynamic branch after the
accepted final recovery-checkpoint-follow-up tail failure.

- New factory:
  `SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryScenarios()`.
- The branch starts from the restored database image and three-frame WAL prefix
  left by the final followup tail rollback.
- It commits a corrected three-frame JSON import that updates the catalog,
  inserts a durable recovery row, updates the prior final-followup row, and
  proves the failed tail insert and malformed tail insert remain absent.
- The example smoke now reports the recovery modes, WAL frame counts, applied
  pages, inserted key, and retained/rejected key state.

## Red-First Evidence

- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryDynamicTest.php`
  - Initial result: fatal missing
    `dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryScenariosFromTailFailureScenarios()`.

## Upstream Parity Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`
  - `wal3-1.*`: WAL frame visibility and rollback consistency after WAL-index
    frame changes.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint.test`
  - `savepoint-1.*` and `savepoint-2.*`: `SAVEPOINT`, `ROLLBACK TO`, and
    release behavior across nested rollback boundaries.

## Focused Evidence

- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryDynamicTest.php`
  - Result: `1 test files, 895 assertions, 0 failures`.
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryDynamicTest.php`
  - Result: `2 test files, 1772 assertions, 0 failures`.
- `php -d memory_limit=2048M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  - Result: `application-wal-rollback-json-dynamic-parity self-test passed`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 6 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryDynamicTest.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
  - Result: no syntax errors.
- `git diff --check -- lanes/libsqlite`
  - Result: passed.

Expected selected movement:

- Adds `895` focused assertions.
- `phpPass` moves `5975230 -> 5976125`.
- Mapped coverage remains `1589 / 1589`.
- Full release/all parity is not claimed.

## Non-Overlap

This starts after the accepted checkpoint-followup recovery checkpoint,
recovery-checkpoint followup, and final-followup tail failure branches. It does
not repeat rollback-disabled reopened-prefix checkpoint followup,
post-checkpoint tail failure/recovery/checkpoint/followup, WAL byte
truncation, VFS savepoint rollback, rollback-journal apply/commit, JSON table
cursor/source/constraint work, row-value dynamic parity, PDO parity,
source-neutral cleanup, or real upstream JSON corpus rows.

## Dependency Closure

No new support component is needed. This reuses native JSON mutation,
savepoint statement journaling, WAL checksum/frame materialization, durable
checkpoint reset/truncate behavior, and source-neutral tenant/key handling.

Root harness: not run - isolated micro-slice.

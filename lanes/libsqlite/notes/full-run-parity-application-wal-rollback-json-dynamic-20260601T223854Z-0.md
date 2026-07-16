## Application WAL Rollback JSON Dynamic Tail Failure Parity

Base accepted HEAD: `9b416406415d610bed7909db13d81b6c90757c7c`.

This slice extends the existing source-neutral application WAL JSON full-run
parity chain with a tail failure branch after the final checkpoint-followup
state:
`dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupTailFailureScenarios()`.

The new branch starts from the accepted deepest final followup state, appends a
three-frame tail batch, applies one catalog mutation, applies one inserted
setting row, then rejects a malformed inserted JSON row. Assertions verify that
the failed statement rolls back only its own frame, the successful tail row is
visible before the outer rollback, and the outer rollback truncates WAL bytes
back to the final followup prefix while restoring the database image and keeping
all previously committed recovery/followup keys.

Source truth used for parity shape:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`
  `wal3-1.*` checkpoint/restart/truncate WAL boundaries.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint.test`
  `savepoint-1.*` and `savepoint-2.*` nested rollback visibility.

Red-first evidence:

- `php -d memory_limit=1536M -r "require 'tools/bootstrap.php'; PortLibs\\LibSqlite\\SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupTailFailureScenarios(1);"`
- Result before implementation: fatal undefined method
  `dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupTailFailureScenarios()`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupTailDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupTailDynamicTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupTailDynamicTest.php`
  - `1 test files, 1059 assertions, 0 failures`
  - PASS rows: `369`
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicFinalFollowupParityTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupTailDynamicTest.php`
  - `3 test files, 2563 assertions, 0 failures`
- `php -d memory_limit=2048M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  - `application-wal-rollback-json-dynamic-parity self-test passed`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 9 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no whitespace errors

Expected dashboard movement: `phpPass` moves `6285636 -> 6286005` (`+369`).
Mapped upstream coverage remains `1589 / 1589`; broad full-lane/release parity
still has the known 16 failures.

Non-overlap: this does not repeat the final followup, earlier tail failure,
tail recovery, rollback-disabled, WAL byte truncation, VFS savepoint rollback,
rollback-journal apply/commit, JSON table/source/constraint, PDO, row-value,
source-neutral cleanup, or real upstream JSON corpus batches. It makes the
post-final-followup malformed tail write countable in a separate bounded focused
test file.

Dependency closure: no new support component is needed. This reuses the existing
native JSON mutation, savepoint statement journaling, WAL checksum/frame
materialization, checkpoint reset/truncate handling, and source-neutral
tenant/key row handling.

Root harness: not run - isolated micro-slice.

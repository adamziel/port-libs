# full-run-parity-application-wal-rollback-json-dynamic-20260601T010823Z-0

Base accepted HEAD: `e274bccd68de6d0be207ea53c6e2f938b9cd38dd`

## Behavior

Added post-tail-recovery-checkpoint followup parity for generic application JSON import WAL rollback flows.

The new `SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupScenarios*()` path starts after the accepted sequence:

- post-recovery restart/truncate checkpoint;
- post-checkpoint followup import;
- malformed post-checkpoint tail rollback;
- corrected post-checkpoint tail recovery commit;
- released restart/truncate checkpoint over that corrected recovery WAL.

It then runs a fresh two-frame JSON import from the released checkpoint database image and reset WAL generation. The assertions prove restart-mode and truncate-mode checkpoint outputs both start from an empty frame generation, append valid frame checksums from the reset header, retain corrected recovery and prior followup rows, and keep the rolled-back post-checkpoint tail row absent.

## Test Growth

Before this slice:

- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php`
- Result: `1 test files, 2301 assertions, 0 failures`.

After this slice:

- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php`
- Result: `1 test files, 2997 assertions, 0 failures`.

Delta: `+278` TestRunner PASS cases and `+696` assertions. `lane-status.json` `phpPass` moves from `4572052` to `4572330`; mapped coverage remains `1589 / 1589`.

## Non-Overlap

This does not repeat accepted app-WAL basic rollback, preexisting WAL rollback, inserted-setting rollback, retry, rollback-disabled followup/recovery/reopened-prefix chains, post-recovery checkpoint, post-checkpoint followup, post-checkpoint tail rollback/recovery, or the already accepted checkpoint after corrected post-checkpoint tail recovery. It owns only the fresh followup import after that released checkpoint.

It also avoids JSON table source/cursor/constraint work, row-value/window behavior, VFS writer/sync/lock/savepoint rollback, rollback-journal apply/commit, source-neutral cleanup, and broad suite metadata.

## Dependency Closure

No new support component is needed. This reuses existing bounded JSON mutation, JSONB handling, savepoint image/WAL frame tracking, WAL checksum/materialization, durable checkpoint sidecar shaping, and application WAL byte helpers.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`: no syntax errors.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php`: `1 test files, 2997 assertions, 0 failures`.
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php`: `2 test files, 17443 assertions, 0 failures`.
- `php -d memory_limit=2048M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`: `application-wal-rollback-json-dynamic-parity self-test passed`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: `1 test files, 3 assertions, 0 failures`.
- `python3 -m json.tool lanes/libsqlite/lane-status.json`: valid JSON.
- `git diff --check -- lanes/libsqlite`: passed.

Root harness: not run - isolated micro-slice.

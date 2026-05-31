# full-run-parity-application-wal-rollback-json-dynamic-20260531T161623Z-0

Base accepted HEAD: `8c7b034bb5fb3d061acb6b56e46103da8721d7a6`.

## Behavior

Added generic application WAL rollback/JSON dynamic parity for checkpointing the corrected post-checkpoint tail recovery transaction.

The new `SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointScenarios*()` path starts after the accepted sequence:

- post-recovery restart/truncate checkpoint;
- post-checkpoint follow-up import;
- malformed post-checkpoint tail rollback;
- corrected post-checkpoint recovery commit.

It then checkpoints the corrected recovery WAL in restart/truncate modes and proves:

- released checkpoints apply only the latest recovery frame images while superseding the earlier follow-up prefix frames;
- restart keeps a reset 32-byte WAL header and truncate removes the WAL sidecar;
- a reader pinned before the final recovery frame keeps the final follow-up page in WAL rather than the database image;
- corrected recovery rows stay retained while the rolled-back tail insert remains absent.

Upstream parity source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test` `wal3-1.*` WAL rollback/savepoint continuation and `/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint.test` `savepoint-1.*` / `savepoint-2.*` rollback-to and release behavior.

## Evidence

Before this slice:

- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php`
- Result: `1 test files, 1606 assertions, 0 failures`.

After this slice:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php`
  - `1 test files, 2301 assertions, 0 failures`
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationImportRollbackWalJsonCurrentNext38Test.php`
  - `3 test files, 15170 assertions, 0 failures`
- `php -d memory_limit=1024M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  - `application-wal-rollback-json-dynamic-parity self-test passed`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`

Focused delta: `+695` assertions in `SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php`, moving selected evidence from `3194686` to `3195381` pass / `0` fail. Mapped coverage remains `1589 / 1589`.

The broader app-WAL family at `1024M` still hit the known existing memory-pressure path in `SQLiteApplicationWalRollbackJsonDynamicParityTest.php`; the changed tail file itself passes at `1024M`, and the broader family passes at `2048M`.

## Non-overlap

This does not repeat accepted app-WAL basic rollback, preexisting WAL rollback, inserted-setting rollback, retry, rollback-disabled follow-up/recovery chains, post-recovery checkpoint, post-checkpoint follow-up, or post-checkpoint tail rollback/recovery. This slice owns only the checkpoint after the corrected post-checkpoint tail recovery commit.

It also avoids JSON table source/cursor/visible-constraint work, row-value/window behavior, e_walhook, lock4, WAL byte truncation primitives, VFS writer/sync/lock/savepoint rollback, rollback-journal apply/commit, and source-neutral cleanup.

## Dependency Closure

No new support component is needed. This reuses existing bounded JSON mutation, JSONB handling, savepoint image/WAL frame tracking, WAL checksum/materialization, durable checkpoint sidecar shaping, and application WAL byte helpers.

Root harness: not run - isolated micro-slice.

# full-run-parity-application-wal-rollback-json-dynamic-20260601T183848Z-0

Base accepted HEAD: `023e8b9061bf873fc923e631b642e47c58fb975d`.

## Behavior

- Added the next application WAL rollback JSON dynamic branch after the accepted final followup tail recovery:
  `SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointScenarios()`.
- The branch checkpoints the corrected final tail-recovery WAL prefix and verifies both released restart/truncate checkpoints and pinned-reader checkpoints.
- The released checkpoint applies the retained prior-recovery frame plus the latest catalog, final-tail-recovery insert, and refreshed final-followup page frames.
- The pinned checkpoint stops before the final commit frame, preserves the WAL sidecar, reports a busy reset, and leaves the final followup page refresh unavailable until the reader drains.
- The dynamic parity example now reports the tail-recovery checkpoint modes, actions, released WAL byte lengths, pinned-reader state, applied pages, materialized page checks, and retained/rejected key state.

## Red-First Evidence

- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointDynamicTest.php`
  - Initial result: fatal missing `dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointScenariosFromTailRecoveryScenarios()`.

## Upstream Parity Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`
  - `wal3-1.*`: WAL frame visibility, restart/truncate reset behavior, and checkpoint state after frame changes.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint.test`
  - `savepoint-1.*` and `savepoint-2.*`: `SAVEPOINT`, `ROLLBACK TO`, and recovery consistency across rollback boundaries.

## Focused Evidence

- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointDynamicTest.php`
  - Result: `1 test files, 824 assertions, 0 failures`.
- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointDynamicTest.php`
  - Result: `2 test files, 1719 assertions, 0 failures`.
- `php -d memory_limit=2048M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  - Result: `application-wal-rollback-json-dynamic-parity self-test passed`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 8 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointDynamicTest.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
  - Result: no syntax errors.
- `git diff --check -- lanes/libsqlite`
  - Result: passed.

Expected selected movement:

- Adds `824` focused assertions in one new TestRunner file.
- `phpPass` moves `6186144 -> 6186968`.
- Mapped coverage remains `1589 / 1589`.
- Full release/all parity is not claimed.

## Non-Overlap

This starts after the accepted final tail recovery branch and only adds its checkpoint application. It does not repeat rollback-disabled reopened-prefix checkpoint followup, post-checkpoint tail failure/recovery/checkpoint/followup branches, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, JSON table cursor/source/constraint work, row-value dynamic parity, PDO parity, source-neutral cleanup, or real upstream JSON corpus rows.

## Dependency Closure

No new support component is needed. This reuses native JSON mutation, savepoint statement journaling, WAL checksum/frame materialization, durable checkpoint restart/truncate behavior, and source-neutral tenant/key handling.

Root harness: not run - isolated micro-slice.

# full-run-parity-application-wal-rollback-json-dynamic-20260601T093812Z-0

Base accepted HEAD: `9495523910adeabd01c9bc2c77431af9d8027200`.

Behavior added:
- Extends `SQLiteJsonImportRollbackWalPlan` with final checkpoint scenarios after a corrected checkpoint-followup tail recovery commits.
- The new generator checkpoints the five-frame recovered WAL generation, verifies restart/truncate reset actions, applies latest catalog/recovery/followup page images, records superseded earlier followup frames, preserves WAL bytes for a pinned reader, and proves the failed followup tail insert remains absent.
- The application smoke now reports the final-recovery checkpoint modes, released WAL lengths, pinned-reader state, applied page order, and rejected-tail key state.

Red-first evidence:
- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointDynamicTest.php`
  - Initial result: fatal missing `dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointScenariosFromRecoveryScenarios()`.

Focused evidence:
- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointDynamicTest.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
  - Result: no syntax errors.
- `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupRecoveryCheckpointDynamicTest.php`
  - Result: `1 test files, 662 assertions, 0 failures`.
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonReopenedCheckpointFollowupDynamicTest.php`
  - Result: `4 test files, 19207 assertions, 0 failures`.
- Combined five-file WAL/JSON dynamic family note: adding the new memory-heavy file to the existing four-file command exhausted the tests' own `1536M` `ini_set()` cap after the new file passed and while loading an older dynamic file, so the acceptance evidence is split into the new focused file plus the existing four-file family.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 5 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  - Result: `application-wal-rollback-json-dynamic-parity self-test passed`.

Non-overlap:
- This starts after accepted checkpoint-followup tail failure/recovery and the 2026-06-01 memory unblock for the full-run dynamic WAL/JSON parity family.
- It does not repeat post-checkpoint tail failure/recovery, checkpoint-followup tail recovery, rollback-disabled reopened-prefix checkpoint followup, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, JSON table cursor/source/constraint work, row-value dynamic parity, or real upstream JSON corpus rows.

Dependency closure:
- No new support component is needed. The slice reuses native JSON mutation, savepoint statement journaling, WAL checksum/frame materialization, durable checkpoint reset/truncate behavior, and source-neutral tenant/key handling.

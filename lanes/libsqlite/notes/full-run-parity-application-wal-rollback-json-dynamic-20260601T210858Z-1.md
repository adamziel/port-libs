## Application WAL Rollback JSON Dynamic Final Followup Parity

Base accepted HEAD: `a741eea1b44d6a0e89ff8e144d4e32e5b55a9f86`.

This slice adds focused PHP coverage for the final existing
`SQLiteJsonImportRollbackWalPlan` full-run helper:
`dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupScenarios()`.

The new test covers the post-checkpoint tail-recovery checkpoint followup path
after a prior failed tail batch has been rolled back, recovered, checkpointed,
and reopened for a final application JSON import. Assertions verify restart
versus truncate reset handling, clean WAL generation, frame and checksum order,
savepoint rollback previews, tenant isolation, JSON text versus JSONB catalog
updates, retained recovery/followup keys, and rejected tail keys staying absent.

Non-overlap: this does not repeat tenant-collision, inserted-setting,
rollback-disabled materialization, post-recovery checkpoint, or reopened-prefix
checkpoint coverage already in `SQLiteApplicationWalRollbackJsonDynamicParityTest.php`.
It makes the deepest existing final-followup chain countable in a separate
bounded focused test file.

Verification:

- Before this slice:
  - `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - `1 test files, 14446 assertions, 0 failures`
  - PASS rows: `8784`
- New focused file:
  - `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicFinalFollowupParityTest.php`
  - `1 test files, 590 assertions, 0 failures`
  - PASS rows: `213`
- Combined focused family:
  - `php -d memory_limit=1536M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicFinalFollowupParityTest.php`
  - `2 test files, 15036 assertions, 0 failures`

Expected dashboard movement: `phpPass` moves `6270884 -> 6271097` (`+213`).
Mapped upstream coverage remains `1589 / 1589`; broad full-lane/release parity
still has the known 16 failures.

Dependency closure: no new support component is needed. This reuses the
existing native JSON mutation, WAL checksum, savepoint rollback, checkpoint
reset/truncate, and source-neutral tenant key handling.

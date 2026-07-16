# Full-run parity application WAL rollback JSON dynamic 20260601T020558Z-0

Base accepted HEAD: `dc8bb5cac377111467dc403c9b9c75704db62cd4`

## Behavior

Added a new application WAL rollback JSON dynamic branch after the accepted
post-checkpoint tail-recovery checkpoint-followup commit:

- a malformed tail batch appends catalog, inserted-row, and malformed JSON
  frames after the committed checkpoint-followup prefix, then rolls back to the
  two-frame prefix;
- a corrected recovery batch starts from that restored database/WAL prefix,
  inserts a durable recovery row, updates the prior checkpoint-followup row,
  and proves the rejected tail insert remains absent.

The new focused test covers 18 generated scenarios across restart/truncate
checkpoint modes, 512/1024 page sizes, JSON text and JSONB catalog updates,
WAL frame continuation/checksums, commit markers, savepoint boundaries, row
retention, and rejected-tail exclusion.

## Non-overlap

This slice is additive after the prior checkpoint-followup branch. It does not
repeat base rollback, preexisting/retry rollback, post-recovery checkpoint,
post-checkpoint followup, post-checkpoint tail failure/recovery/checkpoint,
reopened-prefix checkpoint, JSON table cursor/source/constraint pushdown, VFS
writer/sync/lock, B-tree page move/overflow release, or SELECT SQL text/order
work.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupTailDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupTailDynamicTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupTailDynamicTest.php`
  - `1 test files, 1462 assertions, 0 failures`
  - New focused PASS cases: `573`
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonCheckpointFollowupTailDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `3 test files, 4462 assertions, 0 failures`
  - Focused gate PASS cases: `1722`
- `php -d memory_limit=2048M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  - `application-wal-rollback-json-dynamic-parity self-test passed`
- `jq . lanes/libsqlite/lane-status.json`
  - valid JSON
- `git diff --check -- lanes/libsqlite`
  - no whitespace errors

## Dependency Closure

No new support component is needed. This reuses the existing generic
application JSON import/savepoint planner, WAL append/checksum validation,
checkpoint followup scenario factory, and rollback byte truncation helpers.

## Next

Continue with non-overlapping WAL/VFS transaction durability, broad full-lane
failure triage, or source-neutral cleanup. This branch should be counted as
focused PASS-line growth, not mapped-denominator growth.

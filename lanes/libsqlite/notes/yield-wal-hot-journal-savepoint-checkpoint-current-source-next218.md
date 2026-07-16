# WAL Hot-Journal Savepoint Checkpoint Current Source Next218

## Slice

- Added `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` to gate RESTART/TRUNCATE checkpoint reset publication after the accepted next212 PASSIVE checkpoint reader-pin accounting.
- The planner admits reset only when all requested frames were checkpointed, no current reader pins or stale reader reopens remain, every writer observes the expected database/WAL/writer digests, the savepoint scope is closed, no hot journal remains, dirty caches are absent, and a sync receipt is present.
- Added a Application smoke for copied `wp_options` import checkpoints that may truncate the WAL only after current-source reader and writer fences agree.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext218Test.php`
  - `1 test files, 92 assertions, 0 failures`
  - 92 focused PASS lines.
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next218.php --self-test`
  - `application-wal-hot-journal-savepoint-checkpoint-current-source-next218 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
  - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext218Test.php`
  - no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next218.php`
  - no syntax errors.

## Non-Overlap

This next218 slice finalizes RESTART/TRUNCATE reset admission after next212 PASSIVE checkpoint frame accounting. It does not repeat next212 reader-pin progress, next209 writer fences, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, or checkpoint transaction planning.

## Dependency Closure

No new support component is needed. The behavior reuses next212 checkpoint frame accounting, current-source reader reopen lists, writer generation digests, and sync receipts.

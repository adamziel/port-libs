# WAL Hot-Journal Savepoint Checkpoint Current Source Next208

## Slice

- Added `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` to map post-checkpoint reader-slot reuse from the accepted next206 current-source statement consumer fence.
- Added focused coverage for retaining current reader slots and reopening stale slots when the consumer was quarantined, the reader epoch predates checkpoint publication, the slot frame exceeds the checkpoint frame, database/WAL/page digests are stale, a hot journal identity remains, a savepoint is still open, the shared-lock receipt is missing, or the cache is dirty.
- Added a WordPress smoke for copied `wp_options` import retry readers that reuse only slots tied to current-source statement consumers.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext208Test.php`
  - `1 test files, 72 assertions, 0 failures`
  - 72 focused PASS lines.
- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
  - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext208Test.php`
  - no syntax errors.
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next208.php`
  - no syntax errors.

## Non-Overlap

This next208 slice consumes next206 output and only maps reader-slot reuse after checkpoint publication. It avoids accepted next206 prepared-statement quarantine, next203 page-cache lease checks, WAL byte truncation, WAL sidecar writes, rollback-journal commit/apply, checkpoint transaction planning, and VFS savepoint rollback.

## Dependency Closure

No new support component is needed. The behavior reuses next206 current-source statement fencing plus checkpoint page digests to decide whether a reader slot may survive a hot-journal/savepoint/checkpoint publication.

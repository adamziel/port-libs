# WAL Savepoint Reader Pin Current Next35

Status: focused PHP behavior growth for WAL savepoint rollback when an existing reader keeps a read-mark pin across the current/next boundary.

## Implementation

- Added `SQLiteWalSavepointCheckpointPlan::readerPinCurrentNextAfterRollbackTo()`.
- The planner composes existing savepoint WAL byte truncation, checkpoint mode planning, durable checkpoint results, WAL reader snapshots, and read-mark planning.
- It reports the current pinned reader view from the original WAL, then reports the next reader view from the post-rollback checkpoint result, including read-mark/reset state and dependency tags.
- Added `application-wal-savepoint-reader-pin-current-next35.php` for copied `wp_options` import diagnostics.

## Focused Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointReaderPinCurrentNext35Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures
```

## Non-Overlap

This avoids accepted WAL savepoint byte truncation, savepoint page-image rollback, VFS savepoint rollback apply, WAL checkpoint transactions, checkpoint busy-reader batch29, rollback-journal commit/apply, VFS sync/write/lock clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, Unicode GLOB, B-tree page/freelist clusters, and batch23 WAL append transaction persistence. The new surface is the current reader read-mark pin across a savepoint rollback and the next reader view after checkpoint/reset planning.

## Dependency Closure

No new support component is needed. The slice reuses lane-local WAL parsing/checksums, savepoint WAL rollback metadata, checkpoint durable-result planning, read-mark planning, and reader snapshot page-image primitives.

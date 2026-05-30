# WAL Hot-Journal Savepoint Checkpoint Reader Admission

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a current-source reader admission layer for the hot-journal + savepoint rollback + WAL checkpoint path. It composes the accepted next161 cache-token checkpoint plan, then decides whether reader handles may stay on the checkpoint current-source token or must reopen against the next WAL generation.

This covers a WordPress plugin/options import case where hot-journal recovery restores clean pages, `ROLLBACK TO` restores failed savepoint before-images, a checkpoint current-source token is published, and stale/dirty/pinned/read-ahead readers are fenced before the retry writes continue.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointReaderAdmissionTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 53 assertions, 0 failures
```

New dashboard-visible focused delta: `+53` PASS lines/assertions.

## Non-Overlap

Does not repeat accepted next161 cache-token rebasing, WAL byte truncation, WAL checkpoint transaction writes, VFS writer/sync/lock application, hot rollback-journal application, or prior reader checkpoint snapshots. The new behavior is reader admission across checkpoint current-source and next WAL source tokens after the existing hot-journal/savepoint/checkpoint source has been built.

## Dependency Closure

No new support component is needed. This reuses native WAL parsing, hot-journal recovery modeling, savepoint rollback before-images, durable checkpoint planning, and current-source token bookkeeping already present in the lane.

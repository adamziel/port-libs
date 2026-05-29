# WAL Hot-Journal Savepoint Checkpoint Current Source Next165

## Behavior

- Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a publish-order layer on top of the accepted next162 current-source admission.
- The plan requires hot rollback-journal recovery before checkpoint publication, rolls back WAL frames owned by the failed savepoint, preserves the retained WAL bytes for pinned readers, then publishes the released checkpoint database and restarted/truncated WAL payload for the next reader.
- It rejects stale dirty database checkpoint publication by carrying forward the next162 dirty-page comparison and exposes ordered VFS-style write/truncate/sync/delete operations for clean integration.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext165Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 81 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next165.php
wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next165 self-test passed
```

## Non-Overlap

This slice avoids accepted next162 admission-only coverage by adding publish-order payloads and savepoint release sequencing. It also avoids accepted WAL byte truncation, VFS writer/apply, rollback-journal commit/apply, checkpoint transaction, and hot-journal reader-restart surfaces.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP rollback-journal recovery, WAL parsing/checkpointing, savepoint WAL truncation, and VFS write-plan payload primitives.

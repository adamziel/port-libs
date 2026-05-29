# WAL Checkpoint Reader Savepoint Current Source Next149

This slice adds `SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReplayCurrentSourceNext()`.

The behavior is the current-source reader boundary around `ROLLBACK TO` in WAL mode:

- an already-open reader can remain pinned to the original WAL source, including frames later discarded by savepoint rollback;
- the writer's retained current WAL prefix excludes the rolled-back savepoint tail;
- a next reader after checkpoint sees the retained prefix materialized through the checkpoint database image or preserved WAL, depending on checkpoint mode.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointReaderSavepointCurrentSourceNext149Test.php`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-wal-checkpoint-reader-savepoint-current-source-next149.php`

Non-overlap:

- Avoids accepted WAL byte truncation, VFS savepoint rollback apply, WAL checkpoint transactions, reader restart/truncate, and hot-journal checkpoint clusters.
- This slice does not write files; it is a reader-source planning boundary over existing `SQLiteWal`, `SQLiteSavepointStack`, and checkpoint primitives.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP WAL parsing/checksum, savepoint frame bookkeeping, and checkpoint result generation.

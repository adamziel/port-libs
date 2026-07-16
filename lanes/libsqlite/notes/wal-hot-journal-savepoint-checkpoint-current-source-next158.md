# WAL Hot-Journal Savepoint Checkpoint Current Source Next158

- Scope: WAL/pager/VFS durability behavior for a copied Application SQLite database after an interrupted import leaves both a hot rollback journal and failed savepoint WAL tail frames.
- Implementation: `SQLiteVfsFileWriter::applyWalHotJournalSavepointCheckpointCurrentSourceNext158()` reads the current database, rollback journal, and WAL from the bounded VFS root; applies hot rollback-journal recovery; truncates WAL bytes back to the named savepoint; proves a pinned reader blocks restart reset; applies the released restart checkpoint database image; writes a separate next-generation retry WAL; and syncs database, WAL, and parent directory atomically.
- Focused evidence:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext158Test.php`
  - Result: `1 test files, 54 assertions, 0 failures`.
  - PASS-line delta for this isolated behavior test: `+54`.
- Application smoke:
  - `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next158.php --self-test`
  - Result: `application-wal-hot-journal-savepoint-checkpoint-current-source-next158 self-test passed`.
- Non-overlap: avoids accepted hot-journal checkpoint reader, savepoint byte-truncation, VFS savepoint rollback, rollback-journal commit, checkpoint-reader savepoint, and pager hot-journal savepoint-cache slices by applying the combined hot-journal plus savepoint rollback before released restart checkpoint and next WAL generation writes.
- Dependency closure: no new support component is needed; this composes existing native rollback-journal recovery, WAL savepoint truncation, restart checkpoint, WAL append checksums, and bounded VFS file writer primitives.

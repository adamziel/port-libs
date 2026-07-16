# WAL Hot-Journal Reader Checkpoint Apply Current Source Next125

## Behavior

Adds `SQLiteVfsFileWriter::applyWalCheckpointHotJournalReaderCurrentSourceNext125()` for the composed native VFS path where a copied Application SQLite database opens with:

- a hot rollback journal from an interrupted import,
- a WAL with reader-pinned tail frames,
- a restart/truncate checkpoint attempt that must use the recovered current source without resetting the WAL while the reader is pinned.

The method reads the current database, `-journal`, and `-wal` sidecars from the bounded writer root, verifies the WAL, recovers the hot journal through the existing rollback-journal model, then atomically applies the reader-pinned checkpoint database and WAL bytes through file-handle operations. If the hot journal is blocked by a reserved lock or missing super-journal, it skips writes and leaves sidecars unchanged.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalReaderCheckpointApplyCurrentSourceNext125Test.php
```

Result:

```text
1 test files, 59 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-wal-hot-journal-reader-checkpoint-apply-current-source-next125.php --self-test
```

Result:

```text
application-wal-hot-journal-reader-checkpoint-apply-current-source-next125 self-test passed
```

## Non-Overlap

This avoids accepted WAL checkpoint preview/current-source clusters by applying the composed hot-journal plus reader-pinned checkpoint state through `SQLiteVfsFileWriter`. It does not repeat next120/next122 preview-only reader visibility, accepted WAL byte truncation, checkpoint transactions, rollback-journal apply, savepoint rollback apply, or VFS file writer/locked writer primitives.

## Dependency Closure

No new support component is needed. This reuses native PHP rollback-journal parsing/recovery, WAL checksum parsing, durable checkpoint bytes, and bounded VFS file-handle atomic apply.

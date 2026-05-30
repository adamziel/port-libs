# WAL hot-journal savepoint checkpoint current-source next156

Implemented a bounded WAL/pager current-source apply slice that composes the
existing hot-journal/savepoint/WAL reader-source validation into a durable VFS
operation sequence. The plan preserves the current WAL source for pinned
readers, applies the hot-journal/savepoint retry database image, installs the
next WAL source, deletes the hot journal, and syncs the database, WAL, and
directory.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext156Test.php`
- Result: `1 test files, 51 assertions, 0 failures`

Application smoke:

- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next156.php --self-test`

Non-overlap:

- Avoids accepted VFS rollback-journal apply, VFS savepoint rollback apply,
  WAL byte truncation, checkpoint transaction, and next148 reader-source
  diagnostics. This slice applies the validated current/next WAL source switch
  as a durable VFS operation sequence.

Dependency closure:

- No new support component needed. Reuses next148 pager/WAL source validation,
  `SQLiteWal` parsing/checksum helpers, and the existing native PHP
  `SQLiteVfsFileWriter::applyAtomicOperations()` path.

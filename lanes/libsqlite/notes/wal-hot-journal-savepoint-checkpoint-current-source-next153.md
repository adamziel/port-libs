# WAL Hot-Journal Savepoint Checkpoint Current Source Next153

## Scope

Adds a bounded current-source WAL/pager behavior for a Application copied
`wp_options` import:

- recover hot rollback-journal pages before WAL checkpoint planning;
- roll back a failed plugin-setting savepoint to the retained WAL prefix;
- keep a still-open reader pinned on that rolled-back current WAL source;
- release the reader and checkpoint into a restarted/truncated generation for
  the retry transaction.

This avoids accepted hot-journal restart-only, WAL byte truncation,
rollback-journal apply, savepoint writer, and checkpoint transaction slices by
combining hot recovered database pages with savepoint rollback before the
current-reader checkpoint boundary.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext153Test.php`
  - `1 test files, 60 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next153.php --self-test`
  - `application-wal-hot-journal-savepoint-checkpoint-current-source-next153 self-test passed`

## Dependency Closure

No new support component is needed. The slice reuses lane-local native
rollback-journal hot recovery, WAL parsing, savepoint WAL truncation,
checkpoint durability, and append transaction primitives.

## Next

Follow-up WAL work should move to broader pager/VFS transaction application or
fsync/file-handle durability, not another current-source savepoint checkpoint
wrapper.

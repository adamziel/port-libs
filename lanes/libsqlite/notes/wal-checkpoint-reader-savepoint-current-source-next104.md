# WAL Checkpoint Reader Savepoint Current Source Next104

## Behavior

Adds `SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan`, a bounded
current-source audit for WAL checkpointing after `ROLLBACK TO` a savepoint when
an existing reader was originally pinned inside frames that the savepoint
rollback discards.

The plan verifies the parsed WAL bytes against the current `SQLiteWal` object,
rewinds the current reader to the retained WAL prefix, preserves the pinned
reader during restart/truncate checkpoint attempts, and proves that a reader
opened after releasing the pin sees the checkpointed database image.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointReaderSavepointCurrentSourceNext104Test.php`
- Result: `1 test files, 62 assertions, 0 failures`
- PASS-line delta: `+62`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-wal-checkpoint-reader-savepoint-current-source-next104.php --self-test`

## Non-overlap

This does not repeat accepted WAL savepoint byte truncation, WAL checkpoint
transaction planning, VFS savepoint rollback application, WAL recovery
checkpoint savepoint next100, or reader release next99. The new surface is the
current-source reader rewind audit that connects a reader pinned inside
rolled-back savepoint frames to the retained prefix before checkpoint reset.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP WAL,
savepoint, and durable checkpoint primitives plus bounded sidecar-write
diagnostics.

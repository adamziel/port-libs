# WAL Hot-Journal Savepoint Checkpoint Current Source Next155

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext155Plan`, a bounded current-source planner for the WAL path where:

- a hot rollback journal is recovered before checkpointing;
- a savepoint rollback truncates the usable current WAL source to a retained frame prefix;
- checkpoint database bytes must match the hot-journal database plus that retained WAL prefix;
- an already-open reader may still see post-rollback WAL frames and therefore must reopen before treating checkpoint bytes as its current source.

This is intentionally narrower than accepted next148 full-current-WAL checkpoint comparison: next155 verifies the savepoint rollback frame boundary and the reader/current-source split, without adding another byte-truncation writer or VFS apply wrapper.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext155Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 80 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next155.php --self-test
```

Result:

```text
wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next155 self-test passed
```

## Non-Overlap

Avoids accepted WAL byte truncation, VFS savepoint rollback application, rollback-journal commit/super-journal paths, WAL checkpoint transactions, pager savepoint WAL hot-journal next148, and full-current-WAL hot-journal reader checkpoint next148. This slice only checks checkpoint source selection after a savepoint rollback prefix while preserving stale reader visibility until reopen.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP hot-journal recovery, WAL parsing, reader snapshot, and checkpoint source helpers.

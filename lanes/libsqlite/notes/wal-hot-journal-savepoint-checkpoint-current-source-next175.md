# WAL Hot-Journal Savepoint Checkpoint Current Source Next175

## Behavior

Adds VFS application for the guarded next173 WAL hot-journal/savepoint checkpoint publication path. `SQLiteVfsFileWriter::applyWalHotJournalSavepointCheckpointCurrentSourceNext175()` reads the live database, rollback journal, and WAL sidecars from the writer root, verifies their hashes through the next173 current-source publication guard, and atomically applies the prepared durable database/WAL payloads only when readers are drained and all source bytes still match.

This turns the previous plan-only/hash-admission surface into a bounded write path:

- publish checkpoint database bytes from the prepared current durable payload;
- delete the hot rollback journal;
- publish/truncate/sync the next WAL generation;
- sync the containing directory;
- leave all files untouched when a database, journal, WAL, or reader-drain guard blocks publication.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext175Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 54 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next175.php
```

Result: `status=applied`, `applied=8`, hot journal removed, committed WordPress option bytes present, WAL parses with 2 frames.

## Non-Overlap

Does not repeat accepted next167 token/fingerprint admission, next173 hash planning alone, VFS savepoint rollback, rollback-journal commit, WAL byte truncation, checkpoint transaction planning, or prior hot-journal reader checkpoint surfaces. This slice specifically applies the guarded next173 publication to live VFS files.

## Dependency Closure

No new support component is needed. The slice reuses existing WAL parsing/checkpoint payloads, current-source publication guards, and `SQLiteVfsFileWriter` atomic write/sync/delete primitives.

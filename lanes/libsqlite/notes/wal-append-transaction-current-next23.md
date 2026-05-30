# WAL append transaction current next23

## Behavior

Adds bounded WAL transaction append planning and VFS application for copied Application database imports:

- appends committed and uncommitted WAL transaction frames after an existing WAL;
- preserves SQLite WAL chained checksum seeds from the previous frame or header;
- writes the commit marker only on the final frame of a committed transaction;
- exposes native VFS write/sync/directory-sync operations for the appended WAL bytes;
- keeps uncommitted draft frames visible in the WAL sidecar while excluding them from reader/checkpoint committed state.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalAppendTransactionCurrentNext23Test.php
Focused test run: 1 selected test files (root lock skipped)
50 PASS lines
1 test files, 59 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-wal-append-transaction.php
```

## Status delta

- `phpPass`: +50 verified PASS lines.
- `phpFail`: unchanged at 0.
- Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted WAL checkpoint transactions, VFS file writer, WAL byte truncation, rollback-journal commit/apply, savepoint rollback application, WAL corrupt-boundary recovery, reader checkpoint visibility, or WAL SHM checkpoint restart. The slice covers new WAL append-frame construction plus VFS persistence for committed and uncommitted transaction tails.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP WAL parsing/checksum, WAL reader/checkpoint visibility, and VFS file-handle write primitives.

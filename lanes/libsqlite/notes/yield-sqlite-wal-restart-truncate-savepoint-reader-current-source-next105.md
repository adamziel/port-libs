# WAL Restart/Truncate Savepoint Reader Current Source Next105

Slice: `wal-restart-truncate-savepoint-reader-current-source-next105`

## Behavior

- Adds `SQLiteWalSavepointCheckpointPlan::checkpointRestartTruncateSavepointReaderCurrentSourceNext()`.
- Verifies the supplied current WAL bytes still match the parsed WAL source.
- Verifies current SHM salt and `mxFrame` match the current WAL before planning checkpoint reset.
- Uses the current SHM checkpoint-pinned reader frame as the active savepoint reader.
- Proves both `RESTART` and `TRUNCATE` checkpoint generations preserve the retained WAL prefix while the reader is pinned, then reset/truncate after reader release.
- Rejects stale WAL bytes, SHM salt mismatch, SHM `mxFrame` mismatch, empty page lists, and missing current reader pins.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalRestartTruncateSavepointReaderCurrentSourceNext105Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 65 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +65, from `40110` to `40175`, if accepted.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This avoids the accepted generic WAL reader-pin restart/truncate handoff, WAL byte truncation, VFS savepoint rollback application, WAL checkpoint transaction, and batch100 WAL recovery checkpoint savepoint clusters. The new behavior is specifically the savepoint-scoped restart/truncate current-source path tied to SHM read-mark validation.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded native PHP primitives: `SQLiteWal`, `SQLiteShmIndex`, and `SQLiteSavepointStack`.

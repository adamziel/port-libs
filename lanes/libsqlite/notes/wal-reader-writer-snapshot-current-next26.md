# WAL reader/writer snapshot current next26

## Behavior

Adds bounded WAL reader/writer snapshot boundary planning for copied Application database imports:

- captures the current reader snapshot at the pre-append WAL end frame;
- appends committed and uncommitted writer transaction frames using the existing WAL checksum chain;
- exposes the next reader snapshot at the new committed frame boundary;
- proves committed appended option/index pages are visible to the next reader;
- proves uncommitted appended tail frames remain invisible to the next reader and out-of-range pages report committed-size errors.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderWriterSnapshotCurrentNext26Test.php
Focused test run: 1 selected test files (root lock skipped)
61 PASS lines
1 test files, 64 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-wal-reader-writer-snapshot.php
```

Smoke output reports `currentReaderEndFrame: 2`, `nextReaderEndFrame: 4`, current frame indexes `[1,2,null,null]`, next frame indexes `[1,3,4,null]`, `uncommittedTailVisible: false`, and `nextContainsActivePluginsUpdate: true`.

## Status delta

- `phpPass`: +61 verified PASS lines, from 8739 to 8800 lane-local projected PASS / 0 FAIL.
- Root harness: not run - isolated micro-slice.
- Mapped upstream denominator: unchanged.

## Non-overlap

This does not repeat accepted WAL append transaction persistence, WAL checkpoint transactions, checkpoint current-reader visibility, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, WAL SHM read-mark diagnostics, or corrupt-boundary recovery. The new surface is reader/writer snapshot isolation across appended committed and uncommitted WAL frames.

## Dependency closure

No new support component is needed. The slice reuses native PHP WAL parsing/checksum, append planning, reader snapshot, and VFS file-writer primitives.

# WAL Reader Pin Append Current/Next 66

## Behavior

This slice adds `SQLiteWal::checkpointReaderPinAppendCurrentNext()` for the WAL case where a checkpoint is blocked by a current reader pinned to an older frame, then a writer appends a new committed transaction to the preserved WAL.

The behavior is intentionally disjoint from accepted reader-pin release/retry, byte truncation, checkpoint transaction, VFS writer, and rollback-journal application slices:

- current reader visibility remains fixed at the pinned frame;
- checkpoint output preserves the WAL while the pin blocks reset;
- appended writer frames are checksummed onto the existing WAL image;
- next reader read marks can move to the appended commit frame;
- next reader visibility resolves from the appended WAL frames.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderPinAppendCurrentNext66Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 53 assertions, 0 failures
```

Application smoke:

```bash
php lanes/libsqlite/examples/application-wal-reader-pin-append-current-next66.php
```

Result includes:

```json
{
    "status": "current-reader-pinned-next-writer-appended",
    "checkpoint_busy": true,
    "current_reader_frame": 1,
    "next_reader_frame": 5,
    "next_reader_slot": 2,
    "next_sees_appended_commit": true,
    "dependency": true
}
```

## Status Delta

- `phpPass`: 24610 -> 24663.
- `phpFail`: 0.
- `benchmarkDenominator.mapped`: unchanged; this does not claim a new mapped upstream inventory unit.

## Dependency Closure

No new support component is needed. This reuses existing bounded WAL parser/checksum, read-mark planning, and reader snapshot visibility primitives.

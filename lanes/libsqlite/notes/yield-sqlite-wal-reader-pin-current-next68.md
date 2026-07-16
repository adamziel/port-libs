# WAL Reader Pin Current/Next 68

## Behavior

This slice adds `SQLiteWal::checkpointReaderPinSlotHandoffCurrentNext()` for the WAL-index handoff where a new reader acquires the latest committed read mark while an older current reader still pins checkpoint reset.

The behavior is disjoint from accepted reader-pin release/retry, append-after-pin, byte truncation, checkpoint transaction, VFS writer, rollback-journal, and savepoint writer slices:

- current reader visibility remains fixed at the older pinned frame;
- next reader visibility resolves from the latest committed WAL snapshot;
- checkpoint remains blocked while the older pin is active even after the next reader starts;
- releasing only the older pinned slot leaves the latest next-reader mark in place and allows restart/truncate reset.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderPinCurrentNext68Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures
```

Application smoke:

```bash
php lanes/libsqlite/examples/application-wal-reader-pin-current-next68.php
```

Expected result includes:

```json
{
    "status": "current-reader-pinned-next-reader-active",
    "checkpoint_with_next_busy": true,
    "released_checkpoint_action": "restart_wal",
    "release_unblocks_reset": true,
    "dependency": true
}
```

## Status Delta

- `phpPass`: 25285 -> 25344.
- `phpFail`: 0.
- `benchmarkDenominator.mapped`: unchanged; this does not claim a new mapped upstream inventory unit.

## Dependency Closure

No new support component is needed. This reuses the existing bounded WAL parser/checksum, durable checkpoint, read-mark planning, and reader snapshot visibility primitives.

# WAL checkpoint reader restart snapshot current-source next124

## Behavior

`SQLiteWalCheckpointReaderRestartSnapshotCurrentSourceNextPlan` covers a
reader-pinned restart checkpoint boundary that was not covered by the accepted
reader snapshot, savepoint restart, or checkpoint transaction slices:

- validates that the WAL bytes being planned are the current parsed source;
- preserves the current reader snapshot while `RESTART` checkpoint is busy;
- restarts the WAL generation after reader release;
- appends a next writer transaction to the restarted generation;
- compares current, pinned, released-database, and next-reader sources.

Application path: copied `.ht.sqlite` / `wp_options` import readers can continue
reading an old `active_plugins` snapshot while a later writer appends
`active_plugins` and transient rows to the restarted WAL generation after the
reader releases.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointReaderRestartSnapshotCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 75 assertions, 0 failures
```

Expected lane-local PASS movement: `49426 -> 49501` (`+75`) after integration.
Mapped upstream coverage is unchanged at `606 / 1589`; this is focused PHP
behavior coverage, not a new manifest inventory row.

## Non-overlap

Avoided accepted WAL hot-journal/checkpoint/reader savepoint restart behavior
from batch120/121/clean next122, plus earlier accepted checkpoint snapshot
next108, savepoint restart append next103, checkpoint transaction, byte
truncation, VFS rollback/savepoint apply, and durable checkpoint file-write
clusters. This slice is the plain reader-pinned restart checkpoint to
next-generation append boundary.

## Dependency closure

No new support component is needed. The slice composes existing native PHP
`SQLiteWal::durableCheckpointResult()`, `SQLiteWal::readerSnapshotPageImage()`,
and `SQLiteWalAppendPlan::appendTransactions()` behavior.

## Next

Follow-up WAL work should move to broader pager/VFS transaction application or
fsync/file-handle durability, not another reader restart snapshot variant.

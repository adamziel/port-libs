# WAL Reader Pin Current Next64

## Behavior

`SQLiteWal::checkpointReaderPinCurrentNextHandoff()` extends the existing raw
WAL read-mark checkpoint path with a current-to-next reader handoff:

- preserves a pinned current reader snapshot while a restart checkpoint is busy;
- assigns a reusable next-reader slot to the latest post-checkpoint frame;
- clears the current pinned read mark on release without dropping the next
  reader mark;
- reports the retry checkpoint state after release for restart, passive, and
  truncate modes;
- keeps no-slot and invalid-read-mark cases explicit.

This is intentionally raw `SQLiteWal`/read-mark behavior, not the accepted
`SQLiteShmIndex` restart-retry wrapper.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderPinCurrentNext64Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 64 assertions, 0 failures
```

Dashboard delta for this lane patch: `phpPass` `23341 -> 23405` (`+64` focused
PASS lines). Mapped upstream denominator remains unchanged at `463 / 1589`.

## Application Smoke

`lanes/libsqlite/examples/application-wal-reader-pin-current-next64.php` emits a
copied `wp_options` WAL read-mark handoff summary showing the current reader
frame, next reader frame/slot, released read marks, retry WAL action, and source
columns.

## Non-Overlap

Avoided accepted/queued surfaces: WAL savepoint byte truncation, WAL checkpoint
transactions, SHM restart-retry reader maps, VFS savepoint rollback apply, VFS
rollback-journal apply, VFS file writer/sync/locked writer, parser-level JSON
table work, SQL expression ORDER BY, B-tree page relocation/root collapse, and
batch55 WAL release-then-rollback checkpoint visibility.

## Dependency Closure

No new support component is required. The slice reuses existing native PHP
`SQLiteWal`, `SQLiteWalHeader`, read-mark planning, and durable checkpoint byte
planning.

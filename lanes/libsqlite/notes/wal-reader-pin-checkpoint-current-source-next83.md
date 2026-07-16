# WAL Reader Pin Checkpoint Current Source Next83

## Behavior

Adds `SQLiteWal::checkpointReaderPinRestartCurrentSourceNext()`, a current-source
classification layer over the accepted reader-pin restart/truncate checkpoint
handoff. It preserves the existing current/next/final reader visibility while
also identifying whether each visible page is coming from the old database
image, the preserved WAL, a checkpointed database page while the WAL is still
preserved for a later reader, or the reset database image after all readers
release.

This avoids duplicating the accepted current-next76 restart/truncate handoff:
next83 does not change checkpoint admission, read-mark planning, restart WAL
header generation, or truncate behavior. It adds the missing source selection
evidence needed by VFS/pager application code after a current reader releases
but a next reader still pins the WAL.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderPinCheckpointCurrentSourceNext83Test.php`
  - `1 test files, 59 assertions, 0 failures`
  - 59 PASS lines

## Application Smoke

- `php lanes/libsqlite/examples/application-wal-reader-pin-checkpoint-current-source-next83.php --self-test`
  - Confirms copied `wp_options` WAL restart checkpoint source selection:
    current readers use preserved WAL pages, the next reader sources
    checkpointed pages from the database image while reset is blocked, and
    final readers use the reset database image.

## Dependency Closure

No new support component is needed. This composes existing native PHP WAL,
SHM read-mark, checkpoint, and reader snapshot primitives.

## Non-Overlap

Avoids accepted batch68/batch76 WAL reader-pin restart/truncate handoff,
accepted WAL byte truncation, WAL checkpoint transaction, VFS file writer,
rollback-journal apply, and savepoint rollback application surfaces. The
new behavior is the current-source classification between those existing
checkpoint phases.

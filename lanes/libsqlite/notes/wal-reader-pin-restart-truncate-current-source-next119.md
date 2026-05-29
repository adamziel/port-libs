# WAL Reader Pin Restart/Truncate Current Source Next119

## Behavior

Adds `SQLiteWal::restartTruncateReaderPinCurrentSourceNext()` for a current
WAL sidecar whose read marks pin an older committed frame. The plan verifies:

- restart and truncate checkpoint attempts preserve the WAL while the reader
  pin blocks completion;
- after read marks are released, restart writes a new empty WAL header
  generation and truncate removes the WAL sidecar;
- restart and truncate produce the same checkpointed database image for next
  readers while the pinned current reader keeps its older snapshot.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php`
  - `1 test files, 9900 assertions, 0 failures`
  - focused assertion delta: `9834 -> 9900` (`+66`)
- WordPress smoke:
  - `php lanes/libsqlite/examples/wordpress-wal-reader-pin-restart-truncate-current-source-next119.php --self-test`

## Non-Overlap

This avoids accepted WAL checkpoint transactions, savepoint byte truncation,
VFS savepoint rollback, readmark salt/checksum recovery, and previous
restart/truncate next86/93/97/102 handoffs. The new slice is specifically the
combined current-source read-mark pinned transition from preserved sidecar to
released restart/truncate generations.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded WAL
parsing, durable checkpoint result planning, read-mark planning, and
current-source WAL byte admission.

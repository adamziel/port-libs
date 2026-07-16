# WAL SHM read-mark locking edge next12

## Behavior

- `SQLiteShmIndex` now preserves SHM read-lock bytes alongside read marks.
- Checkpoint planning treats a stale read mark as checkpoint-pinning only when the matching read-lock byte is held.
- Abandoned marks without a read lock are reported as reusable with `read_mark_without_read_lock`, preventing stale SHM slots from blocking WAL checkpoint/reset progress.

## Application smoke

- Added `examples/application-wal-shm-readmark-locking.php` for copied `/srv/www/wp-content/database/.ht.sqlite-shm` diagnostics.
- The smoke shows one live stale reader pinning frame 4, one abandoned stale mark at frame 6 becoming reusable, a latest-commit reader, and an invalid stale slot.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalShmReadMarkLockingTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `PASS ignores abandoned sqlite shm read marks whose read locks are not held`
  - `1 test files, 54 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 9747 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-shm-readmark-locking.php`
  - exited 0 and reported `checkpointPinnedFrame: 4`, `resetBlocked: true`, `readLocks: [false,true,false,true,false]`, and `reusableSlots: [0,2,4]`.

## Dashboard delta

- `lane-status.json` `phpPass` increased by exactly `+1`, from `3796` to `3797`, matching the one new focused `TestRunner` PASS line.
- No mapped upstream denominator change is claimed.

## Non-overlap and dependency closure

- Avoids accepted WAL byte truncation, WAL checkpoint transaction, VFS file writer, VFS lock-state/process-lock, rollback-journal apply/commit, and prior SHM header/read-mark diagnostics.
- No new support component is needed; this reuses the existing bounded `SQLiteShmIndex` parser and SHM checkpoint/read-mark diagnostics.

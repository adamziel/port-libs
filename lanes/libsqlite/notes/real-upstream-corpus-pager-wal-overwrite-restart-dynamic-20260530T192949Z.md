# Real Upstream Pager/WAL Overwrite Restart Dynamic Batch

- Slice: `real-upstream-corpus-pager-wal-dynamic-20260530T192949Z-0`
- Base accepted HEAD: `de394d1a2a5407b1856e89f4b996c5ea3450f50d`
- Source truth: hydrated SQLite upstream checkout at `/home/claude/port-libs/.upstream-cache/libsqlite/test`
- Upstream files:
  - `waloverwrite.test`: `1.1.2..1.1.10` and `1.2.2..1.2.10`
  - `walrestart.test`: `1.0..1.5`

## Coverage

Added `SQLiteRealUpstreamPagerWalOverwriteRestartDynamicTest.php` with 1,165 distinct TestRunner cases and 5,047 behavior assertions.

The batch exercises repeated WAL page overwrites under a small cache-size style frame pattern, copied database plus WAL recovery visibility, savepoint rollback truncation of inner overwrite tails, pre-existing WAL transaction variants, and the restart-checkpoint race where a checkpoint observes `mxFrame` before a smaller writer changes the backfill window.

This is non-overlapping with the accepted `wal.test` warm-body, `wal2.test` header recovery, noop-checkpoint, checkpoint-sync, lock-race, mode-persist, and prior WAL byte-truncation/savepoint/checkpoint publication batches.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalOverwriteRestartDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalOverwriteRestartDynamicTest.php`
  - Result: `1 test files, 5047 assertions, 0 failures`
  - PASS cases: 1,165

## Dependency Closure

No new support component is needed. The batch reuses existing native PHP WAL parsing, checkpoint, reader snapshot, recovery-boundary, and savepoint rollback helpers.

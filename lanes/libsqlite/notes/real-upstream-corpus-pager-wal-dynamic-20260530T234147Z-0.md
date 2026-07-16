# Real upstream pager/WAL dynamic corpus

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260530T234147Z-0`

Session: `port-dev-sqlite-yield-dyn-real-pager-20260530T234147Z`

Base accepted HEAD: `1e28a5dbe5f8813a907a64ec2d403f8339418de7`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/waloverwrite.test`
- Ported `waloverwrite.test` `1.1.1` through `1.1.10` and `1.2.1` through `1.2.10`.
- The upstream scenario creates 20 blob rows, runs repeated WAL-mode overwrite updates with a 5-page cache, verifies recovery from database+WAL copies, then repeats the path around a savepoint rollback where 797-byte savepoint writes are excluded and 798-byte post-checkpoint writes remain visible.

## PHP Coverage Added

- Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walOverwriteRecoveryRows()`.
- Added 1,000 distinct TestRunner cases to `SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`:
  - 2 upstream start variants: empty WAL start and preexisting WAL transaction.
  - 20 rows per variant.
  - 5 update loops per row.
  - 5 behavior checks per row-loop: committed 799-byte WAL recovery, database-only 800-byte baseline, post-checkpoint 798-byte recovery, exclusion of rolled-back 797-byte savepoint writes, and `integrity_check` success.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
  - `1 test files, 24412 assertions, 0 failures`
  - Focused PASS growth: +1000 TestRunner cases from real upstream `waloverwrite.test`.

## Non-Overlap

This extends the existing dynamic pager/WAL corpus beyond prior `wal2.test` and `walckptnoop.test` coverage. It avoids accepted WAL persist-mode, overwrite-restart, noop-checkpoint, crash-recovery, restart/truncate, setlk, protocol, checksum, VFS rollback journal, VFS writer/sync/lock, savepoint byte truncation, and pager master-journal numbered surfaces. The new surface is the upstream WAL overwrite/recovery workload with bounded cache pressure and savepoint rollback exclusion.

## Dependency Closure

No new support component is needed. The slice reuses lane-local pager/WAL dynamic corpus modeling and records the recovery invariants needed by later native pager/VFS transaction application.

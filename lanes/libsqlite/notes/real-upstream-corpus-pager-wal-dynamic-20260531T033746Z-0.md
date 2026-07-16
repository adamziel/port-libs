# real-upstream-corpus-pager-wal-dynamic-20260531T033746Z-0

Added a non-overlapping real upstream WAL readmark cluster from hydrated
`/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`.

Owned upstream sections:

- `wal3.test` `wal3-6.1.1` through `wal3-6.1.7`: readmark 0 after full
  backfill and writer-race fallback to a later readmark slot.
- `wal3.test` `wal3-7.1.1` through `wal3-7.1.4`: stale header retry across
  later readmark slots.
- `wal3.test` `wal3-9.0` through `wal3-9.4`: exclusive readmark update busy
  fallback to a shared lock without modifying the slot value.

Implementation:

- Extended `SQLiteWalVfsDynamicPlan::readmarkSnapshotBoundary()` to model the
  WAL shared-memory readmark slot selection, snapshot frame, checkpoint
  result, lock sequence, retry count, and restart-blocking behavior.
- Extended `SQLiteRealUpstreamPagerWalDynamicTest.php` with 602 additional
  focused PASS cases over the `wal3.test` readmark cluster. This is distinct
  from the accepted `walvfs.test` dynamic SHM boundary coverage.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteWalVfsDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicTest.php`
  passed: `1 test files, 32027 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses existing bounded WAL/VFS
  shared-memory lock modeling in `SQLiteWalVfsDynamicPlan`.

# Real Upstream Pager/WAL Dynamic Corpus

Slice: `real-upstream-corpus-pager-wal-dynamic-20260530T195351Z-0`

Base accepted HEAD: `a279204339e8bc1ec8d0d4db06bea5b6a6d043b5`

Added `SQLiteRealUpstreamPagerWalCrashRecoveryDynamicTest.php`, a focused
real-upstream pager/WAL corpus batch with 1,025 distinct TestRunner PASS cases
and 14,337 focused assertions.

Upstream source truth from `/home/claude/port-libs/.upstream-cache/libsqlite/test`:

- `walcrash.test`: `walcrash-1.*`, `walcrash-2.*`, `walcrash-4.*`,
  `walcrash-5.*`, `walcrash-6.*`, and `walcrash-7.*`
- `walslow.test`: `walslow-3.*`
- `waloverwrite.test`: `1.*`

The cases construct valid WAL images with varied page sizes, committed frame
boundaries, uncommitted crash tails, reader end frames, and checkpoint modes.
They exercise native `SQLiteWal::parse()`, checksum recovery boundary
classification, checkpoint mode application, uncommitted-frame preservation,
and reader visibility. This is non-overlapping with accepted WAL checkpoint
transaction, byte truncation, rollback-journal apply, savepoint rollback, sync
apply, WAL restart/overwrite, sync-matrix, and pager/WAL mode-persist batches
because it targets crash-recovery and corruption-boundary behavior across the
hydrated upstream `walcrash`, `walslow`, and `waloverwrite` sections.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalCrashRecoveryDynamicTest.php`
  - `1 test files, 14337 assertions, 0 failures`
  - `1025` PASS lines

Dependency closure: no new support component is needed; this reuses existing
native WAL parsing/checkpoint/recovery helpers.

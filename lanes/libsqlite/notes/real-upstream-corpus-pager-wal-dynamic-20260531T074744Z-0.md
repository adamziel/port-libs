# real-upstream-corpus-pager-wal-dynamic-20260531T074744Z-0

Slice: `real-upstream-corpus-pager-wal-dynamic-20260531T074744Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walsetlk2.test`
- Upstream sections: `walsetlk2-2.1` through `2.7`, and `walsetlk2-3.1`
  through `3.4`.

Behavior ported:

- `sqlite3_setlk_timeout(db, 2000)` routes blocking locks differently from
  `sqlite3_busy_timeout(db, 2000)` for the rollback-mode writer conflict in
  `walsetlk2-2.1..2.4`.
- WAL-mode writer conflicts with setlk timeout wait through the lock holder in
  `walsetlk2-2.5..2.7`.
- `sqlite3_setlk_timeout(db, -1)` enables indefinite blocking WAL writer waits
  in `walsetlk2-3.1..3.4`.
- The dynamic corpus records the blocking statement, journal mode, lock kind,
  timeout mode, final row set, and dependency tags for 1,000 source-traced
  cases.

Changed files:

- `lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T074744ZTest.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-pager-wal-dynamic-20260531T074744Z-0.md`

Verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T074744ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T074744ZTest.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T074744ZTest.php`
  - `1 test files, 22012 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Expected dashboard movement:

- `phpPass`: `2717884 -> 2718886` from 1,002 newly passing focused
  TestRunner PASS cases.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`.

Non-overlap:

This extends `walsetlk2.test` timeout routing rather than accepted `walsetlk2`
xShmLock sequence rows, `walsetlk` snapshot rows, VFS process locks,
lock-state, WAL byte truncation, rollback-journal apply/commit, checkpoint
transaction, `walhook`, `walro2`, `wal6`, `wal8`, or app-WAL slices.

Dependency closure:

No new support component is needed. The slice reuses lane-local pager/WAL
dynamic corpus modeling and hydrated upstream `walsetlk2.test` source truth.

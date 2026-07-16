# real-upstream-corpus-pager-wal-dynamic-20260530T182344Z-0

Base accepted HEAD: `f9e4e2d5498742752e9304fb10cad66aa60851fc`.

Owned upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
- `wal2-3.0` through `wal2-3.5`: busy READ and RECOVER lock retry behavior.
- `wal2-6.1.*` through `wal2-6.6.*`: WAL/exclusive locking transitions, rollback-journal transition after changing from WAL to DELETE, xShmLock traces in and out of exclusive mode, checkpoint after mode toggles, and failed WAL read-lock retaining exclusive mode.

Lane changes:

- Added `SQLiteRealUpstreamPagerWalDynamicPlan::wal2BusyRecoveryCases()`.
- Added `SQLiteRealUpstreamPagerWalDynamicPlan::wal2ExclusiveLockingCases()`.
- Added `SQLiteRealUpstreamPagerWalExclusiveDynamicTest.php`.

Focused result:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalExclusiveDynamicTest.php`
- Result: `1 test files, 500 assertions, 0 failures`
- PASS-line growth: `39` distinct TestRunner PASS cases.
- Expected `phpPass` movement: `287773 -> 287812`.
- Mapped coverage: unchanged at `1189 / 1589`.

Non-overlap:

- This slice does not repeat accepted WAL noop-checkpoint, WAL checkpoint transaction, WAL byte truncation, VFS rollback journal apply, VFS savepoint rollback apply, rollback-journal commit apply, super-journal commit, walmode/persist, walrestart, or existing wal2 header recovery cases.
- It extends the existing real upstream pager/WAL dynamic plan with uncovered `wal2.test` busy and exclusive-locking subtests.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local WAL/pager lock-sequence modeling and focused TestRunner infrastructure.

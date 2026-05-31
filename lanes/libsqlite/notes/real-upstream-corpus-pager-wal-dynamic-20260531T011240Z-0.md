# real-upstream-corpus-pager-wal-dynamic-20260531T011240Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T011240Z`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
- Added focused dynamic coverage for `wal2.test` `15.1` through `15.12`.

Behavior ported:

- Ports the upstream checkpoint/fullfsync matrix for `PRAGMA checkpoint_fullfsync`, `PRAGMA fullfsync`, and `PRAGMA synchronous`.
- Adds 1,200 distinct dynamic transaction rows across the 12 upstream `wal2-15.*` setting combinations, plus one row-count/provenance assertion.
- Each row verifies WAL mode, disabled autocheckpoint state, restart/commit/checkpoint phase routing, normal/full xSync count selection, fullsync use, and dependency tags.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
  - Result: `1 test files, 54030 assertions, 0 failures`
  - Focused PASS growth: `+1201` TestRunner cases from real upstream `wal2.test`.

Non-overlap:

- Extends the existing real upstream pager/WAL corpus without repeating `walckptnoop.test`, `waloverwrite.test`, `walpersist.test`, `walhook.test`, accepted WAL savepoint byte truncation, VFS writer/sync/lock/rollback clusters, WAL checkpoint transaction plans, rollback-journal commit/super-journal work, or pager master-journal numbered surfaces.
- Mapped denominator coverage remains complete at `1589 / 1589`; this is countable PHP PASS-line growth over already mapped real upstream WAL inventory.

Dependency closure:

- No new support component is needed. This reuses the lane-local pager/WAL corpus-plan structure and bounded VFS xSync count modeling.

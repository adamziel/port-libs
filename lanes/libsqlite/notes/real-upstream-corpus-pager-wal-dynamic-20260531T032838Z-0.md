# real-upstream-corpus-pager-wal-dynamic-20260531T032838Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T032838Z`
Base accepted HEAD: `9f3a6190507c2ea8ee290883ee3ce143ab18c8c9`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal5.test`
- Added focused dynamic coverage for the `wal5.test` `2.4.*` blocking-checkpoint matrix.

Behavior ported:

- Ports the upstream PASSIVE/FULL/RESTART/TRUNCATE checkpoint lock matrix across both checkpoint entry points:
  `PRAGMA wal_checkpoint` and `sqlite3_wal_checkpoint_v2`.
- Adds 1,008 distinct dynamic rows: 14 upstream matrix rows x 2 entry points x 36 iterations.
- Each row verifies the upstream checkpoint result triple, effective checkpoint mode fallback for the typo/passive case, writer/partial-reader/restart-reader lock blocking, busy-handler release phases, attached database context, and dependency tags.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
  - Result: `1 test files, 99077 assertions, 0 failures`
  - Focused PASS growth: `+2017` TestRunner cases from real upstream `wal5.test` (`1008` row-result cases, `1008` busy-handler phase cases, `1` matrix provenance case).

Non-overlap:

- Extends the existing real upstream pager/WAL dynamic corpus without repeating `walckptnoop.test`, `waloverwrite.test`, `walpersist.test`, `walhook.test`, `wal2.test` fullfsync/permission/header cases, `wal3.test` readmark races, `wal8.test` empty-file page-size behavior, accepted WAL byte truncation, VFS writer/sync/lock/rollback clusters, rollback-journal commit/super-journal work, or pager master-journal numbered surfaces.
- Mapped denominator coverage remains complete at `1589 / 1589`; this is countable PHP PASS-line growth over already mapped real upstream WAL inventory.

Dependency closure:

- No new support component is needed. This reuses the lane-local pager/WAL dynamic corpus-plan structure and bounded checkpoint/busy-handler state modeling.

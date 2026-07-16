# real-upstream-corpus-pager-wal-dynamic-20260531T031905Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T031905Z`
Base accepted HEAD: `582d5b219b619868bb38159464dc8e8768230ba8`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walmode.test`
- Added focused dynamic coverage for `walmode.test` `8.1` through `8.22`,
  including the repeated final `8.21` / `8.22` WAL-mode checks.

Behavior ported:

- Ports the upstream attached-database journal-mode matrix for WAL and
  rollback-mode transitions.
- Adds 1,000 distinct dynamic attached-schema rows plus one provenance row.
- Each row verifies main/attached schema journal-mode independence before the
  attached schema explicitly enters WAL, persistence after reopen and separate
  readers, writes preserving each schema mode, and unqualified
  `PRAGMA journal_mode` transitions applying to both schemas.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
  - Result: `1 test files, 86896 assertions, 0 failures`
  - Focused PASS growth: `+1001` TestRunner cases from real upstream
    `walmode.test`.

Non-overlap:

- Extends the existing real upstream pager/WAL corpus without repeating
  `wal2.test`, `wal3.test`, `wal8.test`, `wal9.test`, `walnoshm.test`,
  `walckptnoop.test`, `waloverwrite.test`, `walpersist.test`, `walhook.test`,
  accepted WAL savepoint byte truncation, VFS writer/sync/lock/rollback
  clusters, WAL checkpoint transaction plans, rollback-journal commit,
  super-journal work, or pager master-journal numbered surfaces.
- Mapped denominator coverage remains complete at `1589 / 1589`; this is
  countable PHP PASS-line growth over already mapped real upstream WAL
  inventory.

Dependency closure:

- No new support component is needed. This reuses the lane-local pager/WAL
  corpus-plan structure and generic attached-schema journal-mode modeling.

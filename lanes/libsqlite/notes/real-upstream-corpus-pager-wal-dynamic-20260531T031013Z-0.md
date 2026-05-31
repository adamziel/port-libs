# real-upstream-corpus-pager-wal-dynamic-20260531T031013Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T031013Z`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro2.test`
- Ported sections:
  - `walro2.test` `4.1.1` through `4.1.3`: a `readonly_shm=1` reader sees writer appends after a truncate checkpoint.
  - `walro2.test` `4.2.1` through `4.2.4`: a read-only SHM reader ignores an uncommitted large WAL tail copied from another handle.
  - `walro2.test` `5.1` through `5.3`: a read-only SHM reader remains stable while a checkpoint reads WAL content.
  - `walro2.test` `6.1` through `6.3`: a read-only SHM reader remains stable if a checkpoint truncates during WAL `xRead`.

Focused PHP coverage:

- Added `SQLiteRealUpstreamPagerWalReadonlyShmRefreshDynamicTest.php`.
- Adds 1,000 dynamic read-only SHM refresh PASS cases plus source-citation and non-overlap tests.
- Focused verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadonlyShmRefreshDynamicTest.php`
  - Result: `1 test files, 23009 assertions, 0 failures`
  - TestRunner PASS lines: 1,002.

Non-overlap:

This extends read-only WAL/SHM coverage to later `walro2.test` read-refresh and xRead checkpoint races. It avoids accepted WAL byte truncation, checkpoint transaction, VFS writer/sync/lock-state, rollback commit/apply, `walro.test` 1.*, `walro.test` 2.1, and prior `walro2.test` page-size/cache-refresh matrix coverage.

Dependency closure:

No new support component is needed. This reuses generic `SQLiteWalReadonlyShmPlan` open and checkpoint snapshot planning against hydrated upstream SQLite source sections.

# real-upstream-corpus-pager-wal-dynamic-20260531T024729Z-0

Slice: `real-upstream-corpus-pager-wal-dynamic-20260531T024729Z-0`

Base accepted HEAD: `47e43ea345c857243140b52082e7a664319c5aa0`

Ported a non-overlapping real upstream pager/WAL cluster from the hydrated SQLite checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal8.test`
  - `wal8.test` `1.0` / `1.1`: an empty first handle observes a second handle that initializes WAL before schema creation; later `PRAGMA page_size=4096; VACUUM` succeeds.
  - `wal8.test` `2.0` / `2.1`: an empty first handle observes a second handle that creates schema before switching to WAL; later page-size/VACUUM remains readable.
  - `wal8.test` `3.0` / `3.1`: page-size pragma on the empty first handle does not hide the WAL schema; `sqlite_master` still returns `t1`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal9.test`
  - `wal9.test` `1.0`, `1.6`, `1.7`: a fully checkpointed large WAL with only the first SHM chunk mapped allows a writer transaction to roll back without needing to map the WAL-index tail.

Implementation:

- Added `SQLiteRealUpstreamPagerWalDynamicPlan::wal8Wal9PageSizeMappingCases()` with 1000 source-traced cases:
  - 750 `wal8.test` empty-file/page-size/WAL schema cases.
  - 250 `wal9.test` full-checkpoint/partial-SHM rollback cases.
- Added `SQLiteRealUpstreamPagerWalPageSizeMappingDynamicTest.php` with 1001 distinct TestRunner PASS cases and 20006 focused assertions.

Non-overlap:

- This does not repeat accepted WAL persist/overwrite, wal2/wal3/walrestart, walprotocol/walsetlk, WAL byte truncation, VFS writer/sync/lock/rollback, checkpoint transaction, rollback-journal commit/apply, app-WAL, or pager master-journal numbered surfaces.
- The new behavior is specifically `wal8.test` empty-file page-size handling after another connection initializes WAL and `wal9.test` rollback after a fully checkpointed large WAL with a partial SHM mapping.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php`
  - No syntax errors detected.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalPageSizeMappingDynamicTest.php`
  - No syntax errors detected.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalPageSizeMappingDynamicTest.php`
  - `1 test files, 20006 assertions, 0 failures`
  - 1001 PASS lines.

Dependency closure:

- No new support component is needed. The slice reuses the existing real upstream pager/WAL dynamic corpus plan surface and models the upstream page-size, WAL checkpoint, and SHM mapping invariants as lane-local PHP assertions.

# Real Upstream Corpus Pager/WAL Dynamic Slice

Session: port-dev-sqlite-yield-dyn-real-pager-20260530T231941Z
Base accepted HEAD: 97bde16e3221376c9c3d6c7f9b2330b164322c56

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walckptnoop.test`
- Added coverage for `walckptnoop.test` 1.0 through 1.10.

Behavior added:
- Ported the deterministic `myrandomblob(64)` workload used by upstream test 1.0 into `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walCheckpointNoopRows()`.
- Added 1000 distinct focused TestRunner cases, one per upstream inserted row, verifying 64-byte uppercase hex payload shape and byte checksum from the upstream LCG.
- Added NOOP/PASSIVE WAL checkpoint state cases for upstream tests 1.1 through 1.10, including no-op non-backfill, passive backfill, locked table error, API-level noop, and rollback-mode `-1/-1` behavior.

Non-overlap:
- This extends the existing `wal2.test` dynamic pager/WAL file but does not add new `wal2` cases.
- It avoids accepted WAL setlk snapshot, WAL persist mode, WAL checkpoint transaction, VFS rollback journal, VFS writer, savepoint byte truncation, and pager master-journal numbered surfaces.

Evidence:
- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
  - Result: 1 test file, 9612 assertions, 0 failures.
  - Focused PASS growth: +1020 TestRunner cases from real upstream `walckptnoop.test`.

Dependency closure:
- No new support component is needed. This reuses the existing PHP corpus-plan/test runner structure and models upstream WAL checkpoint state without requiring live Tcl runner mutation.

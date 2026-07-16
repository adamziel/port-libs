# real-upstream-corpus-pager-wal-dynamic-20260530T220757Z-0

Base accepted HEAD: `982e8dd8663ac2abd3a38d17e45a83e32b2f3371`.

Added focused real-upstream pager/WAL coverage in
`SQLiteRealUpstreamPagerWalDynamicTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walvfs.test`
- Scenarios: `walvfs.test` 4.1, 4.2, 5.3, 5.4, 5.5, 5.6, 6.2, 7.1, 8.3, 9.1.

Focused delta:

- 1,002 focused TestRunner PASS cases.
- 20,006 focused assertions.
- 1,000 dynamic WAL/SHM boundary cases vary concrete WAL frame count,
  checkpoint backfill count, and busy-retry attempts across the real upstream
  `walvfs.test` readonly SHM, readmark reclaim, protocol, checkpoint busy,
  cache refresh, and IOERR scenarios.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicTest.php`
  - `1 test files, 20006 assertions, 0 failures`
- Root harness: not run - isolated micro-slice.

Non-overlap:

- Does not add metadata-only runner rows or fabricated upstream scripts.
- Does not repeat accepted VFS file writer, rollback-journal apply, WAL byte
  truncation, checkpoint transaction, pager master journal, or mmap dynamic
  coverage. This batch uses the existing generic `SQLiteWalVfsDynamicPlan`
  surface and real hydrated `walvfs.test` scenarios.

Dependency closure:

- No new support component needed. The batch reuses the existing generic WAL
  VFS dynamic plan and hydrated upstream SQLite `walvfs.test` source.

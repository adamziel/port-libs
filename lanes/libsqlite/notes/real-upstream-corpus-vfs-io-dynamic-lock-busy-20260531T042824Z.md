# Real Upstream Corpus VFS I/O Dynamic Lock Busy 20260531T042824Z

Status: ready for integration from isolated worktree
`9c639ff85ec75b07f4dd143b6bbb0e832cdb6a85`.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/lock.test`
- Scenarios: `lock-2.1`, `lock-2.2`, `lock-2.3.1`, `lock-2.3.2`, and
  `lock-2.4.1`.

Implementation:

- Added `SQLiteVfsIoTrafficPlan::lockBusyCallbackProfile()` for real upstream
  RESERVED-lock contention behavior: writer holds RESERVED, reader SELECT
  remains allowed, requester UPDATE/INSERT/DELETE fails with `SQLITE_BUSY`,
  busy callback runs for an unlocked requester, busy callback is skipped when
  the requester already owns a read lock, and repeated callback count sequences
  are preserved until the callback break.
- Added `SQLiteRealUpstreamCorpusVfsIoLockBusyDynamicTest.php` with `1000`
  dynamic real upstream behavior cases plus upstream ownership and malformed
  input guards.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php`
  - passed
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoLockBusyDynamicTest.php`
  - passed
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoLockBusyDynamicTest.php`
  - passed: `1 test files, 23005 assertions, 0 failures`, `1002` PASS lines

Non-overlap:

- This targets `lock.test` busy callback behavior under RESERVED-lock VFS
  contention.
- It does not repeat accepted `io.test` quick-balance/default-page-size/
  atomic/pager-cache coverage, `ioerr*` fault batches, syscall transient
  `EINTR`, mmap, VFS file writer, lock-state byte-range application, sync-plan,
  rollback-journal apply/commit, WAL checkpoint/savepoint, or JSON/B-tree/SQL
  behavior clusters.

Dependency closure:

- No new support component is required. The batch reuses existing lane-local
  VFS I/O traffic planning and adds a bounded native PHP helper for the
  upstream lock/busy callback contract.

Expected dashboard movement:

- `phpPass`: `2070862 -> 2071864` from `+1002` verified focused PASS lines.
- `benchmarkDenominator.mapped` remains `1589 / 1589`.

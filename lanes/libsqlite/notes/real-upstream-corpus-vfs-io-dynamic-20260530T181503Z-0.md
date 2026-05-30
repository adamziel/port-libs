# real-upstream-corpus-vfs-io-dynamic-20260530T181503Z-0

Accepted base: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walvfs.test`
  - `walvfs-4.0` through `walvfs-4.2`: readonly SHM map failures while opening WAL readers surface as readonly-database errors.
  - `walvfs-5.2` through `walvfs-5.6`: read-mark slots recover to a usable reader after busy/read-only SHM lock failures and explicit readmark reset.
  - `walvfs-6.1` and `walvfs-6.2`: restart attempts that cannot reacquire the shared lock return a locking protocol error.
  - `walvfs-7.1`: checkpointer lock contention reports the `1 -1 -1` busy checkpoint shape.
  - `walvfs-8.2` and `walvfs-8.3`: a version-2 VFS checkpoint flushes stale page-cache state and exposes the newly inserted row.
  - `walvfs-9.1`: readonly-cannot-init plus shared-lock I/O failure reports a disk I/O error.

## Patch

- Added `SQLiteVfsIoDynamicPlan::walShmFaultProfile()` for WAL SHM/readmark/checkpoint fault outcomes from `walvfs.test` sections 4 through 9.
- Extended `SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` with a 280-row focused matrix plus exact upstream citation and malformed-input checks.

## Evidence

- Baseline at `HEAD` for the same focused file:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - `1 test files, 5535 assertions, 0 failures`
- After patch:
  - `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - `1 test files, 11150 assertions, 0 failures`

## Delta

- Focused PHP PASS cases: `+3`
- Focused behavior assertions: `+5615`
- Mapped denominator rows: unchanged; this is accepted-test/assertion growth from real hydrated upstream corpus behavior.

## Non-Overlap

This avoids the earlier VFS IO dynamic append/safe-append/default-page-size/fault-recovery coverage and the accepted VFS file-writer, lock-byte, process-lock, sync-plan/apply, rollback-journal apply/commit, pager/WAL checkpoint transaction, JSON, B-tree, and SQL executor clusters. The new surface is WAL SHM/readmark/checkpoint fault behavior from `walvfs.test` sections 4 through 9.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local VFS IO dynamic and WAL/SHM state modeling; no upstream cache mutation, live service, or shared checkout edit is required.

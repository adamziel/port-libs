# real-upstream-corpus-pager-wal-readonly-shm-refresh-20260531

Slice: `real-upstream-corpus-pager-wal-dynamic-20260531T051637Z-0`
Base accepted HEAD: `597c96169f44cb49bb577675ba5900812102b596`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro2.test`
- Covered sections: `walro2-1.1.2`, `walro2-1.2.2`, `walro2-2.2`, `walro2-2.3.3`, `walro2-3.1.1`, `walro2-3.2.1`, `walro2-3.3.1`, `walro2-3.3.3`, `walro2-4.1.1`, and `walro2-4.1.3`.

## Movement

- Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walReadonlyShmRefreshRows()`.
- Added focused test file `SQLiteRealUpstreamCorpusPagerWalReadonlyShmRefreshTest.php`.
- New focused PASS lines: `1922`.
- New focused behavior assertions: `26890`.
- Expected lane-local selected movement if accepted: `2260947 -> 2262869 pass / 0 fail`.
- Mapped denominator coverage remains `1589 / 1589`.

## Non-Overlap

This slice covers `walro2.test` readonly-shm refresh, zeroed-SHM copy, zero-byte WAL/SHM, truncate-checkpoint cache flush, and WAL wrap recovery. It avoids accepted `walro.test` cache-spill, WAL hook, WAL restart/noop, blocking checkpoint, WAL byte truncation, checkpoint transaction, rollback-journal apply/commit, VFS sync/file writer, app-WAL, JSON table, and source-neutral cleanup slices.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalReadonlyShmRefreshTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalReadonlyShmRefreshTest.php`
  - `1 test files, 26890 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalReadonlyShmCacheSpillTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalFilePermissionDynamicTest.php`
  - `3 test files, 167831 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses the existing generic `SQLiteRealUpstreamPagerWalDynamicCorpusPlan` and the hydrated upstream SQLite checkout as source truth.

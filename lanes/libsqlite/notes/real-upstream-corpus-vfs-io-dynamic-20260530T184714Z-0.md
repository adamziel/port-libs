# real-upstream-corpus-vfs-io-dynamic-20260530T184714Z-0

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T184714Z-0`

Base accepted HEAD: `7e63d4798cb030955a466f3272d59cba9c03648e`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr2.test`
  - `ioerr2-3.*`: transient and persistent I/O failures during a rollback-wrapped DELETE/INSERT/UPDATE batch must preserve the original checksum, leave the pager refcount at zero, and pass integrity check.
  - `ioerr2-4.*`: repeated execution of the same rollback batch after reconnect must preserve the same checksum/integrity/refcount invariants across both persistent modes.
  - `ioerr2-5`: an I/O error inside UPDATE while an outer SELECT is active reports `disk I/O error`, not the historical rollback-abort result.
  - `ioerr2-6`: an xAccess fault for `PRAGMA temp_store_directory` maps to `not a writable directory`.

## PHP Coverage

- Added `SQLiteVfsIoTrafficPlan::ioerr2RollbackInvariant()`.
- Added `SQLiteRealUpstreamCorpusVfsIoerr2DynamicTest.php` with `1,042` focused TestRunner PASS cases and `16,618` assertions:
  - 520 `ioerr2-3.*` transient/persistent fault-index rows.
  - 480 `ioerr2-4.*` repeated transient/persistent fault-index rows.
  - 40 `ioerr2-5` UPDATE-under-SELECT fault rows.
  - 1 `ioerr2-6` temp directory access fault row.
  - 1 malformed-input guard row.

## Non-Overlap

This does not repeat accepted appendvfs, `io.test` default-page-size/device-characteristic traffic, checksum VFS reserve bytes, WAL VFS SHM/readmark faults, ioerr5 persistent pager-error recovery, ioerr6 SHM-full handling, pagerfault hot-journal recovery, VFS writer/sync/lock/rollback/commit application, or WAL/B-tree/JSON/SQL executor clusters. The new surface is specifically `ioerr2.test` rollback/checksum/refcount invariants and the historical UPDATE-under-SELECT I/O error result.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr2DynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr2DynamicTest.php`
  - `1 test files, 16618 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP VFS/pager I/O traffic model and adds a bounded rollback-invariant surface for upstream `ioerr2.test`.

# real-upstream-corpus-vfs-incrblobfault-dynamic-20260531T044558Z-0

## Scope

- Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260531T044558Z-0`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/incrblobfault.test`
  - `incrblobfault-1`: `sqlite3_blob_reopen()` to a high rowid under fault simulation.
  - `incrblobfault-2`: `sqlite3_blob_reopen()` to negative rowid returns either "no such rowid" or disk I/O error.
  - `incrblobfault-3`: incremental BLOB open/read returns `hello world` under fault simulation.

## Behavior Added

- Added `SQLiteVfsIoTrafficPlan::incrementalBlobFaultProfile()` for bounded native modeling of incremental-BLOB VFS fault behavior.
- Added `SQLiteRealUpstreamCorpusVfsIncrementalBlobFaultDynamicTest.php`.
- The focused test ports 3,360 dynamic cases across the three upstream sections, read lengths, `xRead`/`xWrite`/`xSync`/`xTruncate` fault operations, positive/negative/high rowid reopen targets, and 120 fault indexes.
- Assertions cover reopen/read result routing, disk I/O error publication, rowid-not-found behavior, returned BLOB payload, handle cleanup, connection error state, final integrity, open-file cleanup, upstream citations, and malformed-input guards.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIncrementalBlobFaultDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIncrementalBlobFaultDynamicTest.php`
  - `1 test files, 70567 assertions, 0 failures`
  - `3361` focused PASS lines

## Non-Overlap

This slice avoids accepted `io.test`, `ioerr*.test`, `sysfault.test`, `backup_ioerr.test`, `tempfault.test`, `mmapfault.test`, `pagerfault2/3`, append-vfs, checksum VFS, WAL VFS, VFS writer/sync/lock/rollback-journal clusters, WAL checkpoint/savepoint clusters, B-tree, JSON, SQL, and PRAGMA corpus batches. The owned upstream surface is `incrblobfault.test` incremental-BLOB reopen/read fault recovery.

## Dependency Closure

No new support component is needed. The patch reuses the existing VFS I/O traffic planning surface and adds one bounded native PHP helper for incremental-BLOB fault recovery.

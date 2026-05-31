# real-upstream-corpus-vfs-io-dynamic-20260531T030537Z-0

## Scope

- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/delete_db.test`
- Covered upstream sections:
  - `delete_db.test` `1.1.0` and `1.1.1`: rollback-journal sidecars are removed with the database.
  - `delete_db.test` `1.2.0` and `1.2.1`: WAL and SHM sidecars are removed with the database.
  - `delete_db.test` `1.3.0` and `1.3.1`: multiplex rollback-journal chunks are removed.
  - `delete_db.test` `1.4.0` and `1.4.1`: multiplex WAL/SHM chunks are removed.
  - `delete_db.test` `2.1.0` through `2.4.1`: 8.3-name journal, WAL, SHM, and multiplex sidecars are removed.
  - `delete_db.test` `3.0` and `3.1`: directory target returns `SQLITE_ERROR`; missing nested target returns `SQLITE_OK`.

## Behavior Added

- Added `SQLiteVfsIoDynamicPlan::deleteDatabaseSidecarProfile()`.
- Added `SQLiteRealUpstreamCorpusVfsDeleteDatabaseDynamicTest.php` with 1,120 dynamic TestRunner cases plus directory/missing-path, citation, volume, and malformed-input guards.
- The model records sidecar filenames, deletion order, post-delete file inventory, 8.3 naming differences, multiplex chunk removal, result code, reason, and dependency markers.

## Non-Overlap

This ports `delete_db.test` database-deletion sidecar behavior. It avoids the accepted VFS writer, locked writer, process lock, sync apply, rollback-journal apply/commit, super-journal, safe-delete `journal2.test`, WAL checkpoint/savepoint, mmap, quota/quota2, `io.test` device matrix, and `walvfs.test` SHM/readmark clusters.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsDeleteDatabaseDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsDeleteDatabaseDynamicTest.php`
  - `1 test files, 24104 assertions, 0 failures`
  - `1125` focused PASS lines

## Dependency Closure

No new support component is needed. This reuses the lane-local VFS I/O dynamic model and adds bounded native PHP sidecar inventory/deletion behavior for the upstream `sqlite3_delete_database()` test surface.

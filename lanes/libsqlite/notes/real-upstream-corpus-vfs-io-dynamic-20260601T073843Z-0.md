# real-upstream-corpus-vfs-io-dynamic-20260601T073843Z-0

## Source truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/tkt2409.test`.
- Ported behavior: blocked cache-spill under a peer read lock uses pager-cache heap fallback during ordinary statements, leaves `sqlite3_errcode()` at `SQLITE_OK`, preserves integrity, and does not auto-rollback. The separate COMMIT boundary still reports `database is locked` while the peer read lock remains and succeeds after that read lock is released.
- Covered upstream sections: `tkt2409-1.1` through `tkt2409-1.4`, `tkt2409-2.1` through `tkt2409-2.3`, `tkt2409-3.1` through `tkt2409-3.5`, and `tkt2409-4.1` through `tkt2409-4.5`.

## Patch delta

- Added `SQLiteVfsIoDynamicPlan::blockedCacheSpillReadLockProfile()` for the `tkt2409.test` read-lock/cache-spill and COMMIT busy boundary.
- Added `SQLiteRealUpstreamCorpusVfsIoTkt2409CacheSpillDynamicTest.php` with 1,000 dynamic behavior cases plus upstream citation, malformed-input, non-overlap, and dependency-closure checks.
- Added `application-vfs-cache-spill-read-lock.php` as a generic application smoke for the same behavior.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoTkt2409CacheSpillDynamicTest.php` passed.
- `php -l lanes/libsqlite/examples/application-vfs-cache-spill-read-lock.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoTkt2409CacheSpillDynamicTest.php` passed: `1 test files, 33265 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-vfs-cache-spill-read-lock.php --self-test` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 5 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `git diff --check -- lanes/libsqlite` passed.

## Non-overlap

This slice owns only upstream `tkt2409.test` blocked cache-spill/read-lock behavior and the related COMMIT busy boundary. It avoids accepted `io.test` sync/device/default-page-size/cache-retention batches, PRAGMA `cache_spill` parsing, VFS writer/sync/lock, rollback-journal apply/commit, win32 lock retry, mmap, reservebytes, ioerr, pagerfault, WAL, B-tree, JSON, and SELECT clusters.

## Dependency closure

No new support component is needed. The batch reuses `SQLiteVfsIoDynamicPlan` and the hydrated upstream SQLite `tkt2409.test` source file.

## real-upstream-corpus-vfs-io-dynamic-20260530T224544Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pageropt.test`
- Scenarios: `pageropt-1.3`, `pageropt-1.4`, `pageropt-1.5`, and `pageropt-1.6`.

Behavior ported:

- Added `SQLiteVfsIoDynamicPlan::pageroptCacheReuseProfile()` for the upstream pager optimization contract where a same-connection read after insert performs no database reads, an external reader does not invalidate the original pager cache, an external writer invalidates the cache and forces a bounded database reread, and the next read is served from the refreshed cache.
- Added `SQLiteRealUpstreamCorpusVfsPageroptCacheDynamicTest.php` with 1,000 dynamic TestRunner cases across page sizes, payload sizes, cache sizes, same-connection/external-reader/external-writer/mmap variants, plus source-citation and malformed-input guards.

Focused assertion count:

- `1,000` dynamic TestRunner PASS cases.
- `25,005` behavior assertions from the focused test file.

Non-overlap:

- This claims only `pageropt.test` `pageropt-1.3` through `pageropt-1.6`.
- It avoids existing `pageropt-2.*` overflow/freelist coverage, `io.test` traffic/default-page-size/cache-spill coverage, `avfs.test`, `cksumvfs.test`, `walvfs.test`, `ioerr*`, `pagerfault*`, VFS writer/sync/lock/rollback-journal apply, WAL checkpoint/savepoint, B-tree overflow/freelist release, JSON, SQL executor, and source-neutral cleanup surfaces.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local VFS I/O dynamic planner surface and adds a bounded native PHP pager cache reuse/invalidation model from real upstream SQLite pager optimization tests.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPageroptCacheDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPageroptCacheDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

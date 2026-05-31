# real-upstream-corpus-vfs-io-dynamic-20260531T042323Z-0

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T042323Z-0`.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`

Upstream scenarios covered:

- `io.test` `io-3.1` through `io-3.3`: IOCAP_SEQUENTIAL cache-spill behavior writes a grown database image without pre-commit journal fsyncs, then commits with one database sync.
- `io.test` `io-4.1` through `io-4.3.4`: IOCAP_SAFE_APPEND omits the extra journal-header sync, writes `0xFFFFFFFF` in the first journal header nRec field, and keeps one journal header across repeated cache spills.

Implementation:

- Added `SQLiteRealUpstreamCorpusVfsIoSequentialSafeAppendDynamicTest.php` with 1,002 focused TestRunner PASS cases and 31,005 behavior assertions.
- No production source change was needed; this reuses existing `SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile()` behavior and the hydrated upstream `io.test` source.

Non-overlap:

- This owns `io.test` sequential and safe-append cache-spill sync/header behavior only.
- It does not repeat accepted VFS mmap, reopen-fault, ioerr, journal2 SAFE_DELETE, atomic2, quick-balance `io-1.*`, atomic-admission `io-2.*`, default-page-size `io-5.*`, pager-cache retention `io-6.*`, walvfs, VFS file-writer, lock-state, process-lock, sync-plan/apply, rollback-journal apply/commit, or WAL checkpoint/savepoint clusters.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoSequentialSafeAppendDynamicTest.php` - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoSequentialSafeAppendDynamicTest.php` - `1 test files, 31005 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` - passed.

Dependency closure:

- No new support component is needed. The slice reuses the existing native VFS I/O dynamic plan and real upstream `io.test` source truth.

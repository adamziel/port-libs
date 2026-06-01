# real-upstream-corpus-vfs-io-dynamic-20260601T150119Z-0

Slice: real upstream VFS/pager-cache pressure coverage from the hydrated SQLite corpus.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pcache.test`
- `pcache-1.1`: reset global `pcache_stats`.
- `pcache-1.2`: opening a primary connection with `PRAGMA cache_size=12` holds one recyclable schema page.
- `pcache-1.3` through `pcache-1.4`: dirty schema pages in a write transaction are not recyclable.
- `pcache-1.5` through `pcache-1.6.1`: a peer connection raises the global max, then a peer read transaction pins its page.
- `pcache-1.6.2` through `pcache-1.8`: the writer may exceed the global max while the peer page is pinned; peer rollback frees that page immediately instead of recycling it.
- `pcache-1.9` through `pcache-1.15`: commit recycles to the global max, closing the peer restores the primary max, cache-size changes free excess recyclable pages, direct header mutation reloads schema cache pages, and rereads repopulate within the reduced max.

Patch:

- Added `SQLiteVfsIoDynamicPlan::pageCachePressureProfile()` for the upstream `pcache.test` pressure sequence and parameterized variants.
- Added `SQLiteRealUpstreamCorpusVfsPcacheDynamicTest.php` with 1004 focused PASS cases and 28030 behavior assertions, including 1000 deterministic dynamic pressure profiles plus source-truth, malformed-input, and non-overlap checks.
- Added `application-vfs-pcache-pressure.php` as a generic application smoke for the same behavior.
- Updated `lane-status.json` by the verified PASS-line delta: `5941212 -> 5942216`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPcacheDynamicTest.php` passed.
- `php -l lanes/libsqlite/examples/application-vfs-pcache-pressure.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPcacheDynamicTest.php` passed: `1 test files, 28030 assertions, 0 failures` with 1004 PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 6 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-vfs-pcache-pressure.php --self-test` passed.
- `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

This owns only upstream `pcache.test` `pcache-1.1` through `pcache-1.15` global page-cache pressure, peer pinned-page over-limit free, commit recycling, cache resize, direct header reload, and reread transitions. It avoids accepted VFS writer/sync/lock/file-control, `shmlock.test`, `sharedlock.test`, `lock6.test`, `lock7.test`, `tkt2409.test` blocked cache-spill read-lock, `io.test` sync/device/default page-size/cache-retention, mmap, ioerr, diskfull, win32, delete_db, multiplex, quota, and pager/WAL transaction clusters.

Dependency closure:

No new support component is needed. The patch reuses the source-neutral `SQLiteVfsIoDynamicPlan` real-corpus surface and hydrated upstream `pcache.test` source truth.

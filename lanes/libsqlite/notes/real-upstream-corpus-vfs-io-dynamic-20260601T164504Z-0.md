# real-upstream-corpus-vfs-io-dynamic-20260601T164504Z-0

## Source truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pcache2.test`
- Upstream scenarios owned: `pcache2-1.1`, `pcache2-1.2`, `pcache2-1.3`, `pcache2-1.4`
- Behavior ported: configured `sqlite3_config_pagecache 6000 100` pool status reset, primary and peer connection `SQLITE_STATUS_PAGECACHE_USED` slot accounting, and primary dirty-write pressure that stops at the primary cache cap instead of consuming pagecache space reserved for the peer connection.

## Patch

- Added `SQLiteVfsIoDynamicPlan::pageCachePoolReservationProfile()` with exact canonical upstream `pcache2.test` status samples `{0 0 0}`, primary open `2`, peer open `4`, and final write-burst `{0 13 13}`.
- Added `SQLiteRealUpstreamCorpusVfsPcache2DynamicTest.php` with 1 canonical case, 1000 dynamic reservation matrix cases, source-truth citation, malformed option guards, and non-overlap/dependency closure assertions.
- Updated `lane-status.json` expected `phpPass` from `6102011` to `6103015` for the 1004 new focused PASS cases.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPcache2DynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPcache2DynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPcache2DynamicTest.php`
  - `1 test files, 24040 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPcacheDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPcache2DynamicTest.php`
  - `2 test files, 52070 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 7 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Non-overlap

This slice owns upstream `pcache2.test` pagecache-pool reservation behavior. It avoids the already accepted `pcache.test` global over-limit pressure coverage, `tkt2409` cache-spill fallback, VFS writer/sync/lock/file-control, rollback-journal, WAL, mmap, quota, syscall, diskfull, win32, delete_db, and shared-lock clusters.

## Dependency closure

No new support component is needed. The slice reuses the source-neutral VFS I/O dynamic planner surface and the hydrated upstream `pcache2.test` source truth.

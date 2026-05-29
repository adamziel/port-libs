# Pager master-journal reader-cache current-source next351-358

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next351-358` after accepted next343-350.

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` with next351 through next358 fences for status-counter, pagecache-size, pagecache-recycle, scratch-spill, lookaside-hit, lookaside-miss-size, pcache-refcount, and memory-used reader-cache tokens. Reader tickets that predate those current pager/runtime cache counters reopen before reusing recovered master-journal cache pages.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext358Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext350Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext358Test.php`
- `git diff --check`

Non-overlap: builds only on accepted next343-350 page-cache overflow, scratch allocator, lookaside, pcache dirty-limit, mmap read-limit, sorter reference, worker-thread, and memory-alarm reader-cache fences. It does not repeat rollback-journal apply, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, PRAGMA schema, trigger, or encoding behavior.

Next slice: continue with pager master-journal reader-cache current-source runtime/cache invalidation fences after next358.

# Pager master-journal reader-cache current-source next343-350

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next343-350` after ready next335-342.

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` with next343 through next350 fences for page-cache overflow, scratch allocator, lookaside, pcache dirty-limit, mmap read-limit, sorter reference, worker-thread, and memory-alarm reader-cache tokens. Reader tickets that predate those current pager/runtime settings must reopen before reusing recovered master-journal cache pages.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext350Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext342Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext350Test.php`
- `git diff --check`

Non-overlap: builds only on accepted next335-342 threads, optimize, analysis-limit, heap-limit, page-size, max-page-count, and locking-proxy-file reader-cache fences. It does not repeat rollback-journal apply, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or unrelated PRAGMA schema behavior.

Next slice: continue with pager master-journal reader-cache current-source runtime/cache invalidation fences after next350.

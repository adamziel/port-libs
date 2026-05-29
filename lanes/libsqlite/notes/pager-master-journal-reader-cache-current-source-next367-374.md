# Pager master-journal reader-cache current-source next367-374

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next367-374` after ready next359-366.

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` with next367 through next374 fences for statement busy, memory-used, scanstatus, reprepare, run, sort, autoindex, and fullscan reader-cache tokens. Reader tickets that predate those current statement/runtime counters must reopen before reusing recovered master-journal cache pages.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext374Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext366Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext374Test.php`
- `git diff --check`

Non-overlap: builds only on accepted next359-366 memory/status reader-cache fences. It does not repeat rollback-journal apply, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, PRAGMA schema, or unrelated planner behavior.

Next slice: continue with pager master-journal reader-cache current-source statement/cache invalidation fences after next374.

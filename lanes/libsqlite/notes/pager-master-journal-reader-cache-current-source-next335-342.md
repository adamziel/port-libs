# Pager master-journal reader-cache current-source next335-342

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next335-342` after ready next331-334.

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` with next335 through next342 fences for threads, optimize, analysis limit, heap limits, page-size, max-page-count, and locking-proxy-file reader-cache tokens. Reader tickets that predate those current pager/runtime settings must reopen before reusing recovered master-journal cache pages.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext342Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext334Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext342Test.php`
- `git diff --check`

Non-overlap: builds only on accepted next331-334 reverse-scan-order, defensive, writable-schema, and journal-size-limit reader-cache fences. It does not repeat rollback-journal apply, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or unrelated PRAGMA schema behavior.

Next slice: continue with pager master-journal reader-cache current-source runtime/cache invalidation fences after next342.

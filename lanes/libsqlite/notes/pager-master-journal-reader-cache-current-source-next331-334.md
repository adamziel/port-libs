# Pager master-journal reader-cache current-source next331-334

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next331-334` after ready next327-330.

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` with next331 through next334 fences for reverse scan order, defensive mode, writable schema, and journal size limit reader-cache tokens. A reader ticket that predates any of those current-source pager settings must reopen before reusing recovered master-journal cache pages.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext334Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext334Test.php`
- `git diff --check`

Non-overlap: builds only on accepted next327-330 fullfsync, legacy file format, and read-uncommitted reader-cache fences. It does not repeat rollback-journal apply, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or unrelated PRAGMA schema behavior.

Next slice: continue with pager master-journal reader-cache current-source PRAGMA/runtime option fences after next334.

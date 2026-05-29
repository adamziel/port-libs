# Pager master-journal reader-cache current-source next383-398

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next383-398` as a direct follow-on to accepted next375-382.

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` with next383 through next398 statement reader-cache fences. Recovered master-journal pages may not be reused by prepared statement readers when scanstatus detail, VM lifecycle, bound-parameter, SQL text, readonly-schema, or busy-state tokens predate the current source.

Validation:
- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext398Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext398Test.php`

Non-overlap: builds only on accepted next375-382 statement status fences. It does not repeat rollback-journal apply, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, PRAGMA schema, or earlier pager reader-cache token work.

Next slice: continue pager master-journal reader-cache current-source fences after next398 only if new pager/statement tokens are distinct from scanstatus, VM, SQL text, and busy-state coverage.

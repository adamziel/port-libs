# Pager Master-Journal Reader Cache Current Source Next226

## Behavior

`SQLitePagerMasterJournalReaderCacheCurrentSourceNext226Plan` layers on the accepted next219 database page-count fence and adds a page-1 header counter fence for master-journal reader-cache reuse.

SQLite readers should not reuse recovered current-source cache entries when the cached/read-ticket `database_change_counter` and `version_valid_for` pair is stale or internally incoherent. This slice marks those pages/readers for reopen after attached master-journal recovery while preserving the existing next219 admissions for member journals, database file token, header digest, and page count.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext226Test.php`
- Result: `1 test files, 76 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next226.php --self-test`
- Result: `application-pager-master-journal-reader-cache-current-source-next226 self-test passed`

## Non-Overlap

This does not repeat accepted next219 page-count invalidation, next217 database-header digest admission, next218 cleanup-token fencing, raw master-journal bytes, member token/header/order fences, rollback-journal apply, WAL, VFS writer, or super-journal commit behavior.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded pager/master-journal reader-cache plans and adds a native PHP metadata fence only.

# Pager Master-Journal Reader Cache Current Source Next231

## Behavior

`SQLitePagerMasterJournalReaderCacheCurrentSourceNext231Plan` layers on the accepted next226 header-counter fence and adds a page-1 freelist header fence for master-journal reader-cache reuse.

SQLite readers should not reuse recovered current-source cache entries when their cached/read-ticket freelist trunk page and freelist page count are stale, internally incoherent, or point past the recovered database page count. This keeps a copied Application database reader from reusing alloptions/plugin cache pages after an attached master-journal rollback changes free-page state.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext231Test.php`
- Result: `1 test files, 78 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next231.php --self-test`
- Result: `application-pager-master-journal-reader-cache-current-source-next231 self-test passed`

## Non-Overlap

This does not repeat accepted next226 change-counter/version-valid-for fencing, next219 page-count invalidation, next217 database-header digest admission, next218 cleanup-token fencing, raw master-journal bytes, member token/header/order fences, rollback-journal apply, WAL, VFS writer, or super-journal commit behavior.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded pager/master-journal reader-cache plans and adds a native PHP freelist header metadata fence only.

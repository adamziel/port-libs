# Pager master-journal reader-cache current source next225

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next225`.

Behavior: adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext225Plan`, layered after accepted `next219` page-count admission, to fence reader-cache reuse on SQLite page-1 cache-validity counters: change-counter and version-valid-for. This prevents a reader ticket from reusing a page after master-journal hot recovery when the header digest/page count path is admitted but the cache-validity tuple has advanced.

Application smoke: `examples/application-pager-master-journal-reader-cache-current-source-next225.php` models a copied Application import with attached user data. Schema/root pages remain reusable when validity counters match the recovered current source, while `active_plugins` reopens when its reader-cache ticket carries the pre-recovery change-counter/version-valid-for tuple.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext225Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext225Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next225.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext225Test.php`
  - `1 test files, 75 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next225.php`
  - `application-pager-master-journal-reader-cache-current-source-next225 self-test passed`

Dashboard delta: expected `phpPass` movement is `+75` from `108262` to `108337`; mapped upstream coverage is unchanged because this is lane-local pager behavior evidence rather than a newly mapped upstream manifest row.

Dependency closure: no new support component needed; this reuses native PHP pager/master-journal reader-cache planning and the accepted rollback-journal/current-source cache-ticket chain.

Non-overlap: avoids accepted batch198 `next219` page-count invalidation and the earlier reader-cache fences for database header digest, database/master file tokens, raw master-journal bytes, member tokens/headers/order, rollback-journal apply, WAL, VFS writer, and super-journal commit behavior.

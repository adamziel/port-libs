# Pager Master Journal Reader Cache Current Source Next250

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next250`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It extends the accepted pager/master-journal reader-cache current-source family by requiring a current master-journal reader snapshot token before a recovered reader-cache page can be reused. A page whose bytes and current-source provenance still look valid is fenced when the reader snapshot was opened before the current master-journal recovery completed.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next250.php` covers a copied `wp_options` database where the schema cache remains reusable, an options-root cache page reopens because its reader snapshot predates the recovered master journal, and an `active_plugins` reader reopens because its source provenance is stale.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext250Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext250Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next250.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next250.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext250Test.php`
  - `1 test files, 96 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next250.php`
  - `wordpress-pager-master-journal-reader-cache-current-source-next250 self-test passed`

Non-overlap: avoids accepted next247 pager reader-cache generation, next246 version-vector, next244 page-image digest receipt, next243 current-source provenance, next240 statement schema-root, rollback-journal apply/commit, super-journal commit, WAL checkpoint/savepoint byte truncation, VFS writer/sync/lock, B-tree, JSON, SQL executor, and encoding behavior. This slice only adds the reader-snapshot admission fence layered after existing master-journal current-source checks.

Dependency closure: no new support component is needed; this reuses lane-local pager master-journal reader-cache and current-source token primitives.

Next task: connect this reader-snapshot fence to a future native pager transaction object once the lane owns direct reader-cache entries instead of plan arrays.

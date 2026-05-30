# Consolidate Pager Reader Cache Snapshot Fence

Status: numbered production helper cleanup for the pager reader-cache master-journal reader snapshot fence.

This slice renames the numbered production entrypoint to `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::masterJournalReaderSnapshotFence()`. The returned status, dependency, operation, and non-overlap strings still preserve the established next250 evidence keys so downstream generated arrays and receipts keep their observable shape.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-snapshot-fence.php` covers a copied `wp_options` database where the schema cache remains reusable, an options-root cache page reopens because its reader snapshot predates the recovered master journal, and an `active_plugins` reader reopens because its source provenance is stale.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMasterJournalReaderSnapshotFenceTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMasterJournalReaderSnapshotFenceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-snapshot-fence.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-snapshot-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMasterJournalReaderSnapshotFenceTest.php`
  - `1 test files, 96 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCache*.php`
  - `149 test files, 9983 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-snapshot-fence.php`
  - `wordpress-pager-master-journal-reader-cache-snapshot-fence self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed

Non-overlap: avoids accepted next247 pager reader-cache generation, next246 version-vector, next244 page-image digest receipt, next243 current-source provenance, next240 statement schema-root, rollback-journal apply/commit, super-journal commit, WAL checkpoint/savepoint byte truncation, VFS writer/sync/lock, B-tree, JSON, SQL executor, and encoding behavior. This slice only adds the reader-snapshot admission fence layered after existing master-journal current-source checks.

Dependency closure: no new support component is needed; this reuses lane-local pager master-journal reader-cache and current-source token primitives.

Next task: connect this reader-snapshot fence to a future native pager transaction object once the lane owns direct reader-cache entries instead of plan arrays.

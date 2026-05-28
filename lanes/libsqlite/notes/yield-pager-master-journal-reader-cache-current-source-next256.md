# Pager master-journal reader-cache current-source next256

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next256`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext256Plan`. It layers a page-1 database-header schema-cookie fence on top of the accepted next253 database header change-counter fence. A prepared reader-cache page can cross the recovered master-journal boundary only when current-source provenance, the database header change counter, and the database header schema-cookie tickets all match the current source.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next256.php` models copied `wp_options` recovery where schema, option-root, and `active_plugins` readers are reopened for different stale-current-source causes before import resumes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext256Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext256Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next256.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext256Test.php`
  - `1 test files, 119 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next256.php`
  - `wordpress-pager-master-journal-reader-cache-current-source-next256 self-test passed`

Expected dashboard delta: `phpPass` moves from `134837` to `134956` from 119 newly passing focused PASS lines. Mapped upstream coverage remains `674 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next253 database-header change-counter, next252 master-member manifest, next248 page-owner map, next245 rootpage map, next244 page-image receipts, rollback-journal apply/commit/super-journal, WAL checkpoint/savepoint/restart/truncate, VFS writer/sync/lock, B-tree, JSON, SQL executor, and encoding behavior. The new surface is specifically the database-header schema-cookie ticket required before master-journal reader-cache reuse.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache current-source primitives.

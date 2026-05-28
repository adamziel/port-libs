# Pager master-journal reader-cache current-source next177

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next177`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext177Plan`. It models the pager boundary after master-journal recovery where reader-cache reuse must be fenced by the recovered page-1 header ticket, not just by the page image or master-journal membership. The plan extracts the current change counter, database size, first freelist trunk, freelist count, and schema cookie from page 1, derives a header signature, invalidates stale/dirty/pinned reader-cache entries, refreshes clean stale pages with a current header ticket, and forces the next read to reopen when its ticket predates the recovered header.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next177.php` covers copied `wp_options` reads after master-journal recovery where the stale `active_plugins` reader has a pre-recovery header signature and the dirty plugin cache page is rejected before cached bytes can be served.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext177Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext177Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next177.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext177Test.php`
  - `1 test files, 97 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next177.php`
  - `wordpress-pager-master-journal-reader-cache-current-source-next177 self-test passed`

Expected dashboard delta: `phpPass` moves from `82455` to `82552` from 97 newly passing focused PASS lines. Mapped upstream coverage remains `613 / 1589`; this is a focused pager reader-cache behavior over existing master-journal/current-source inventory rather than a fresh manifest-backed denominator row.

Non-overlap: this avoids accepted pager master-journal reader-cache next158/159/160/161/162/163/164/165/166/167/168/169/170/171/172/173/174 clusters by adding page-1 header change-counter/schema-cookie/freelist ticket fencing before reader-cache reuse. It does not repeat next174 rollback-journal source digests, next173 master-membership tickets, next172 attached-database scoping, next158 stale page-image refresh, pager hot-cache/cache-spill/savepoint behavior, WAL checkpoint/savepoint/hot-journal behavior, VFS writer/sync/lock behavior, B-tree, JSON, SELECT, trigger, planner, or encoding surfaces.

Dependency closure: no new support component is needed. The slice reuses lane-local pager recovery, page-image, and native page-1 header parsing primitives.

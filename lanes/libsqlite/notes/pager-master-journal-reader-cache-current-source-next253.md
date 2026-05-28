# Pager master-journal reader-cache current-source next253

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next253`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext253Plan`. It models a pager reader-cache fence after master-journal recovery where otherwise-admitted cache pages must still match the recovered database header change-counter before a next reader can reuse them. Pages whose current-source provenance already failed continue to reopen for the older reason; pages with matching provenance but stale change-counter tickets reopen before the next read.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next253.php` models a copied `wp_options` import where the schema page remains reusable, while a stale options root page with an old header change-counter and an `active_plugins` page with stale current-source provenance reopen before plugin import resumes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext253Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext253Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next253.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext253Test.php`
  - `1 test files, 96 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next253.php`
  - `wordpress-pager-master-journal-reader-cache-current-source-next253 self-test passed`

Expected dashboard delta: `phpPass` moves from `131296` to `131392` from 96 newly passing focused assertions. Mapped upstream coverage remains `663 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next249 reader-cache source handoff, next250 master-journal reader snapshot, earlier reader-cache provenance/schema/read-transaction fences, rollback-journal apply/commit/super-journal, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, B-tree, JSON, SELECT, and encoding surfaces. The new behavior is specifically the database header change-counter cache fence after master-journal current-source recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache, current-source provenance, statement schema-root, and read-transaction fences.

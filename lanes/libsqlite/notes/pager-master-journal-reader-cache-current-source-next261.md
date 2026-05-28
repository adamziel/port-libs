# Pager Master Journal Reader Cache Current Source Next261

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next261`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext261Plan`. It extends the accepted next258 pager spill-drain reader-cache fence by requiring cache rows and reader tickets to carry the rollback-journal reader-source token used by the current master-journal recovery. Pages admitted by the next258 chain are still reopened when their cached rollback-journal reader source predates the current recovered source.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next261.php` models a copied `wp_options` recovery where the schema and `active_plugins` pages keep current cache hits, while the `wp_options` root page is reopened because it was cached from an older rollback-journal reader source.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext261Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext261Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext261Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext261Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next261.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next261.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext261Test.php`
  - `1 test files, 69 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next261.php --self-test`
  - `wordpress-pager-master-journal-reader-cache-current-source-next261 self-test passed`

Expected dashboard delta: `phpPass` moves from `142008` to `142077` from 69 newly passing focused PASS lines. Mapped upstream coverage remains `708 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next258 pager spill-drain, next254 recovery receipt, next251 reader snapshot, next247 generation, next243 provenance, rollback-journal apply/commit, master-journal savepoint/statement recovery, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock, B-tree, JSON, SELECT, trigger, PRAGMA, and encoding surfaces. The new surface is specifically the rollback-journal reader-source fence layered after spill-drain admission.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache and rollback-journal recovery source-token primitives.

Next task: wire rollback-journal reader-source tokens into the native pager cache owner once broader pager transaction execution owns reader-cache entries directly.

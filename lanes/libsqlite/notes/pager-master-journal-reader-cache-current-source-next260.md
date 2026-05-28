# Pager Master-Journal Reader Cache Current Source Next260

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next260`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext260Plan`. It layers on the accepted next257 recovered-page checksum receipt fence and adds a current-source reader-ticket fence for cached pages after attached master-journal recovery. Pages that pass the recovered checksum receipt can still be rejected when the cached page or reader ticket belongs to a prior current source, forcing a reader reopen before WordPress import reads continue.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next260.php` models copied `wp_options` recovery where the schema and `active_plugins` cache rows can continue, but a stale `wp_options` root reader ticket reopens after master-journal recovery even though the recovered page bytes match.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext260Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext260Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next260.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext260Test.php`
  - `1 test files, 59 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next260.php --self-test`
  - `wordpress-pager-master-journal-reader-cache-current-source-next260 self-test passed`

Expected dashboard delta: `phpPass` moves from `140230` to `140289` from 59 newly passing focused PASS lines. Mapped upstream coverage remains `694 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: this avoids accepted next257 recovered-page checksum receipt fencing, next254 recovery receipts, next251 snapshots, next247 reader-cache generation, page-count/header/schema-cookie/provenance fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, and encoding behavior. The new behavior is specifically the post-checksum current-source reader-ticket admission boundary.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache primitives and adds only a native PHP admission token.

# Pager Master-Journal Reader Cache Current Source Next244

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next244`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext244Plan`. It layers on the accepted next240 statement schema-root fence and adds a page-image digest receipt fence for master-journal reader-cache reuse. Reader-cache pages that otherwise pass the current-source admission are retained or refreshed only when their cache row and read ticket carry the current recovered page-image digest receipt; stale digest receipts force reader reopen before serving the next source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next244.php` models a copied `wp_options` database where schema/options pages can stay hot after master-journal recovery, but a stale `active_plugins` read ticket reopens because its page-image digest receipt predates the recovered current source.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext244Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext244Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next244.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext244Test.php`
  - `1 test files, 68 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next244.php --self-test`
  - `application-pager-master-journal-reader-cache-current-source-next244 self-test passed`

Expected dashboard delta: `phpPass` moves from `122940` to `123008` from 68 newly passing focused PASS lines. Mapped upstream coverage remains `647 / 1589`; this is additional focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next240 statement schema-root tokens, next236 schema-reparse, next233 read-transaction, next229 pager-cache source, next226 page-1 header counters, next224 reader-lease, next218 cleanup-token, page-count invalidation, member-journal token/header/order fences, rollback-journal apply, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, and encoding behavior. The new surface is page-image digest receipt admission for reader-cache reuse after the current master-journal source is already recovered.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache and statement schema-root current-source primitives.

Next task: wire this receipt fence into the broader pager reader-cache owner once the lane has a native pager transaction executor that stores page-image digest receipts directly.

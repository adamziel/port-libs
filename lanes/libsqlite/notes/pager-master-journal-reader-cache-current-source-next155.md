# Pager master-journal reader-cache current-source next155

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next155`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext155Plan`. It models the pager boundary after master-journal hot recovery where reader cache entries may be reused only when their source id, epoch, reader generation, shared-lock state, dirty state, and page image match the recovered current source. Clean stale reader pages are refreshed, while pinned stale, dirty, old-source, old-epoch, and old-generation entries are invalidated before the next reader observes recovered pages.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next155.php` covers copied `wp_options` repair where an `active_plugins` reader page is refreshed after master-journal recovery, a stale pinned autoload-index reader page is invalidated, and the next reads use the recovered current source.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext155Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext155Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next155.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext155Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next155.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `68972` to `69052` from 80 newly passing focused PASS lines. Mapped upstream coverage remains `607 / 1589`; this is focused pager/master-journal reader-cache behavior over existing pager inventory rather than a fresh manifest-backed upstream row.

Non-overlap: this avoids accepted master-journal hot-cache next136, cache-spill master next145, pager savepoint/hot-journal cache next128, WAL hot-journal/checkpoint/savepoint clusters, rollback-journal apply/commit/super-journal paths, VFS writer/sync/lock clusters, B-tree freeblock/freelist/page-move/root-collapse/overflow clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is specifically reader-cache validation and refresh before the next read after master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local master-journal member validation and pager current-source/page-cache modeling.

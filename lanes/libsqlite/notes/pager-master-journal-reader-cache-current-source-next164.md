# Pager master-journal reader cache current-source next164

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next164`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext164Plan`. It extends the accepted next161 reader-cache master-journal digest fence with page-1 current-source header metadata: after master-journal recovery updates the database change counter, schema cookie, or version-valid-for value, reader-cache entries from the old source are invalidated even when their page image and master-journal digest otherwise look reusable. Retained and refreshable cache pages still remain available when their header fence matches the recovered source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next164.php` models a copied `wp_options` database where master-journal recovery changes header metadata, refreshes a clean root page, rejects stale `active_plugins` and dirty plugin-settings reader cache entries, and captures the next write from the recovered header source.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext164Test.php`
  - `1 test files, 89 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext164Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext164Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next164.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next164.php`
  - `application-pager-master-journal-reader-cache-current-source-next164 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `73438` to `73527` for the 89 verified PASS lines in this isolated worktree. Mapped upstream coverage remains `610 / 1589`; this is additional focused pager behavior over the existing master-journal reader-cache inventory, not a fresh manifest-backed upstream row.

Non-overlap: avoids accepted next158/159/160/161 master-journal reader-cache membership/digest behavior, master-journal hot/cache recovery, statement-journal savepoint recovery, cache-spill savepoint behavior, WAL hot-journal/checkpoint/savepoint slices, VFS rollback/commit/sync/lock writer clusters, B-tree freeblock/freelist/page-move clusters, JSON table cursor/constraint/source clusters, SELECT SQL text/group/order/subquery clusters, and encoding Unicode GLOB work. The new behavior is specifically the page-1 header change-counter/schema-cookie/version-valid-for fence for reader cache admission after master-journal recovery.

Dependency closure: no new support component is needed. The patch reuses lane-local pager/master-journal cache primitives and bounded PHP page-image/header decoding.

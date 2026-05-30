# Pager Master-Journal Reader-Cache Current Source Next242

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next242`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It layers a prepared-statement snapshot fence after the accepted next239 shared schema-cache generation fence. A reader-cache page that already passes master-journal cleanup, reader lease, pager-cache-source, read-transaction, schema-reparse, and shared-generation checks still reopens when its statement snapshot token predates the recovered current source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next242.php` models copied `wp_options` import behavior where the schema page remains cached, but stale options-root and `active_plugins` readers reopen after master-journal recovery before plugin import continues.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext242Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next242.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext242Test.php`
  - `1 test files, 88 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next242.php`
  - `application-pager-master-journal-reader-cache-current-source-next242 self-test passed`

Expected dashboard delta: `phpPass` moves from `121683` to `121771` from 88 newly passing focused PASS lines. Mapped upstream coverage remains `645 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next239 shared-cache generation fences, next236 schema-reparse fences, next235 change-counter fences, next233 read-transaction fences, next232 database-path fences, next229 pager-cache-source fences, next224 reader-lease fences, next218 cleanup-token fences, raw master-journal bytes, member-token/header/order fences, VFS writer/sync/lock clusters, rollback-journal apply/commit, WAL checkpoint/savepoint/restart/truncate visibility, super-journal commit, B-tree, JSON, SELECT, PRAGMA, trigger, and encoding behavior. The new behavior is only the prepared-statement snapshot boundary before reusing reader-cache pages after master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache, schema-reparse, shared-generation, and read-transaction primitives.

Next task: wire this statement snapshot fence into the eventual native pager cache owner when broader pager transaction execution owns prepared statement readers directly.

# Pager master-journal reader-cache current-source next239

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next239`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It layers a shared schema-cache generation fence after the accepted next236 schema-reparse admission. A reader-cache page that already passes master-journal cleanup, reader lease, pager-cache-source, read-transaction, and schema-reparse checks still reopens when its shared-cache generation token predates the recovered current source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next239.php` models copied `wp_options` import behavior where the schema page remains cached, but stale option-root and `active_plugins` readers reopen after master-journal recovery before plugin import continues.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext239Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next239.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext239Test.php`
  - `1 test files, 81 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next239.php`
  - `application-pager-master-journal-reader-cache-current-source-next239 self-test passed`

Expected dashboard delta: `phpPass` moves from `119121` to `119202` from 81 newly passing focused PASS lines. Mapped upstream coverage remains `642 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next236 schema-reparse fences, next235 change-counter fences, next234 application metadata fences, next233 read-transaction fences, next232 database-path fences, next229 pager-cache-source fences, next224 reader-lease fences, next219 page-count fences, next218 cleanup-token fences, payload/header/file-token fences, rollback-journal apply/commit/super-journal, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, B-tree, JSON, SELECT, PRAGMA, trigger, and encoding surfaces. The new behavior is specifically the shared schema-cache generation boundary before reusing reader-cache pages after master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache, schema-reparse, and read-transaction primitives.

Next task: continue with broader pager/VFS transaction application or a distinct WAL durability edge; avoid another reader-cache token wrapper unless it guards a new current-source invariant with focused tests.

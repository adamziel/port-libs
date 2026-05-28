# Pager Master Journal Reader Cache Current Source Next258

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next258`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext258Plan`. It layers on the accepted next254 master-journal recovery receipt fence and adds a pager spill-drain token. Reader-cache pages that otherwise pass recovery receipt, reader snapshot, and generation admission reopen when their cache row or next read ticket predates the current dirty-page spill drain after master-journal recovery.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next258.php --self-test` models copied `wp_options` recovery where the schema and `active_plugins` pages stay reusable, but a stale `wp_options` root page reopens because it was cached before the current pager spill drain completed.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext258Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext258Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext258Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext258Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next258.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next258.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext258Test.php`
  - `1 test files, 68 assertions, 0 failures`
  - 68 focused PASS lines

Expected dashboard delta: `phpPass` moves from `136435` to `136503`. Mapped upstream coverage remains `680 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next254 recovery receipt, next251 reader snapshots, next247 pager-reader generation, next243 provenance, rollback-journal apply/commit/super-journal, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, B-tree, JSON, SQL executor, PRAGMA, trigger, encoding, and suite-runner evidence. The new behavior is specifically the pager spill-drain fence after current master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache, recovery receipt, reader snapshot, and dirty-page spill-drain tokens.

Next task: wire the spill-drain token into broader pager/VFS transaction application when the lane owns native dirty-page spill execution directly.

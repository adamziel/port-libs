# Pager master-journal reader-cache current-source next236

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next236`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It layers a schema-reparse token fence after the accepted reader-cache source checks. Reader-cache pages that already pass master-journal cleanup, reader lease, pager-cache-source, and read-transaction checks are still reopened when their schema-reparse token predates the recovered current source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next236.php` models copied `wp_options` import behavior where schema and option-root pages can stay cached after master-journal recovery, but a stale `active_plugins` schema read is reopened before plugin import resumes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext236Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next236.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext236Test.php`
  - `1 test files, 60 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next236.php`
  - `application-pager-master-journal-reader-cache-current-source-next236 self-test passed`

Expected dashboard delta: `phpPass` moves from `116842` to `116902` from 60 newly passing focused PASS lines. Mapped upstream coverage remains `639 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next233 read-transaction fences, next232 database-path fences, next229 pager-cache-source fences, next224 reader-lease fences, next219 page-count fences, next218 cleanup-token fences, payload/header/file-token fences, rollback-journal apply/commit/super-journal, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, B-tree, JSON, SELECT, PRAGMA, trigger, and encoding surfaces. The new behavior is specifically the schema-reparse ticket boundary before reusing reader-cache pages after master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache and read-transaction primitives.

Next task: continue with broader pager/VFS transaction application or a distinct WAL durability edge; avoid another reader-cache token wrapper unless it guards a new current-source invariant with focused tests.

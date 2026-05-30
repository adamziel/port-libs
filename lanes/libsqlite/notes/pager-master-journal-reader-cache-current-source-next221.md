# Pager master-journal reader cache current-source next221

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next221`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext221Plan`. It extends the accepted next217 database-header digest fence with a structured recovered page-1 pager header ticket: `change_counter`, `schema_cookie`, `version_valid_for`, and `page_count`. Reader-cache pages and next-read tickets are admitted only when that tuple matches the current master-journal recovery source, so stale schema-cookie/change-counter/page-count tickets force reopen even after inherited master-journal, member-journal, database-file-token, and opaque header-digest fences have run.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-pager-header-ticket-fence.php` covers copied `wp_options` behavior where a recovered master-journal current source retains the schema page, refreshes the alloptions page, and reopens active plugin/rewrite-rule readers whose structured pager header ticket predates the recovered schema cookie/change counter.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCachePagerHeaderTicketFenceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-pager-header-ticket-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCachePagerHeaderTicketFenceTest.php`
  - `1 test files, 50 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-pager-header-ticket-fence.php --self-test`
  - `wordpress-pager-master-journal-reader-cache-current-source-next221 self-test passed`

Expected dashboard delta: `phpPass` moves from `106763` to `106813` from 50 newly passing focused PASS lines. Mapped upstream coverage remains `624 / 1589`; this is focused pager behavior over existing master-journal reader-cache inventory rather than a fresh upstream denominator row.

Non-overlap: avoids accepted batch196 pager master-journal reader-cache next217 database-header digest behavior, earlier next212 database file-token, next209 master-journal bytes, member-token/header/order fences, WAL checkpoint/savepoint/hot-journal clusters, VFS writer/sync/lock clusters, B-tree, JSON, SQL executor, PRAGMA, trigger, and encoding clusters. The new behavior is specifically structured page-1 pager header ticket fencing after current-source master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache primitives and adds only structured ticket validation/admission on top.

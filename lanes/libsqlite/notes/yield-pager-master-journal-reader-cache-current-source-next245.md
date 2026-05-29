# Pager master-journal reader-cache current-source next245

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next245`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It composes the accepted next242 statement-snapshot reader-cache fence with a narrower sqlite_schema rootpage-map fence after master-journal recovery. A cache page can cross into the next current source only when the prepared statement snapshot and the recovered rootpage map both match the current source; stale rootpage maps reopen readers even if the statement snapshot token is otherwise current.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next245.php` models copied `wp_options` import behavior where the schema page remains reusable, while a stale `wp_options` rootpage-map ticket and a stale `active_plugins` statement snapshot reopen before plugin import continues.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext245Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next245.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext245Test.php`
  - `1 test files, 76 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next245.php`
  - `wordpress-pager-master-journal-reader-cache-current-source-next245 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `124032` to `124108` from 76 newly passing focused PASS lines. `benchmarkDenominator.mapped` remains `649 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh upstream manifest row.

Non-overlap: avoids accepted next242 statement-snapshot, next239 shared cache generation, next236 schema reparse, next233 read transaction, next229 pager-cache source, next224 reader lease, next218 cleanup-token admission, next166/next226 earlier reader-cache fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/lock/sync, B-tree page relocation, JSON table, SELECT, and encoding behavior. The new surface is specifically the recovered sqlite_schema rootpage-map token required before prepared-statement reader-cache reuse.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache, statement snapshot, schema-reparse, shared-generation, and read-transaction fences.

Next task: continue pager work only on a non-overlapping transaction-application or durability edge; otherwise pivot to another current-source closure bucket with focused assertions.

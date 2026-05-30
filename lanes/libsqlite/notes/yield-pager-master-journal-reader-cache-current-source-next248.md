# Pager master-journal reader-cache current-source next248

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next248`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It composes the accepted next245 rootpage-map reader-cache fence with a narrower B-tree page-owner map fence after master-journal recovery. A cache page can cross into the next current source only when the recovered sqlite_schema rootpage map and page-owner map both match the current source; stale page ownership reopens readers even if the rootpage map token is otherwise current.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next248.php` models copied `wp_options` import behavior where the schema page remains reusable, while a stale `wp_options` page-owner ticket and a stale `active_plugins` rootpage reader reopen before plugin import resumes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext248Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next248.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext248Test.php`
  - `1 test files, 74 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next248.php`
  - `application-pager-master-journal-reader-cache-current-source-next248 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `126252` to `126326` from 74 newly passing focused PASS lines. `benchmarkDenominator.mapped` remains `651 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh upstream manifest row.

Non-overlap: avoids accepted next245 rootpage-map token checks, next244 page-image digest receipts, next242 statement snapshots, next239 shared cache generation, next236 schema reparse, next233 read transactions, next229 pager-cache source, next224 reader leases, next218 cleanup-token admission, page-count/header fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/lock/sync, B-tree page relocation/freeblock materialization, JSON table, SELECT, and encoding behavior. The new surface is specifically the recovered B-tree page-owner map token required before prepared-statement reader-cache reuse.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache, rootpage-map, statement snapshot, schema-reparse, shared-generation, and read-transaction fences.

Next task: continue pager work only on a non-overlapping transaction-application or durability edge; otherwise pivot to another current-source closure bucket with focused assertions.

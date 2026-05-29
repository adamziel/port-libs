# Pager master-journal reader-cache current-source next249

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next249`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It composes the accepted next246 current-source version-vector fence with a narrower reader-cache source-handoff token. A cache page can cross master-journal recovery only when the recovered source is handed off to the reader cache using the current token; stale handoff tokens force reader reopen even when earlier version-vector, provenance, statement-root, schema, and journal-member checks pass.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next249.php` models copied `wp_options` import behavior where schema/options pages remain reusable, while a stale `active_plugins` reader cache reopens before plugin import continues.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext249Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next249.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext249Test.php`
  - `1 test files, 62 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next249.php`
  - `wordpress-pager-master-journal-reader-cache-current-source-next249 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `127481` to `127543` from 62 newly passing focused PASS lines. `benchmarkDenominator.mapped` remains `654 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next246 version-vector, next243 provenance, statement-root/schema/read-transaction tokens, master-journal byte/token/member fences, next246 WordPress smoke behavior, WAL checkpoint/savepoint, rollback-journal apply/commit, VFS writer/sync/lock, B-tree, JSON, SQL executor, and encoding behavior. The new surface is specifically the recovered source-handoff token required before reader-cache reuse.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache, current-source version-vector, provenance, schema, read-transaction, and journal-member fences.

Next task: connect this handoff token to native pager transaction ownership once the pager executor stores recovered source handoff receipts directly.

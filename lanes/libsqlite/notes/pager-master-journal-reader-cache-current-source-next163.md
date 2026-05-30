# Pager master-journal reader-cache current-source next163

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next163`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext163Plan`. It models the pager read-side transition after current master-journal recovery when the next reader source is available. Cached reader pages are reused only when the cached image matches the recovered current source and the next source page digest is unchanged. Changed next-source pages fall back to next-source bytes, while pinned changed pages and dirty reader-cache pages are reported as blockers before stale bytes can be served.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next163.php` covers a copied `wp_options` reader that keeps the unchanged schema page cache, reads changed `active_plugins` from the next source, and reports dirty plugin settings cache before another reader observes it.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext163Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext163Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next163.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext163Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next163.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `72664` to `72744` from 80 newly passing focused PASS lines in this isolated worktree. Mapped upstream coverage remains `609 / 1589`; this is focused pager/master-journal reader-cache current-to-next behavior over existing journal/cache inventory rather than a fresh upstream denominator row.

Non-overlap: this avoids accepted pager master-journal reader-cache next160, master-journal hot cache next136, master-journal cache recovery next122, statement-journal savepoint master next123, hot-journal savepoint/cache-spill slices, rollback-journal apply/commit/super-journal clusters, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, and B-tree/JSON/SELECT/encoding surfaces. The new behavior is specifically current-to-next reader source selection after current master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local master-journal member parsing and pager-cache current-to-next digest fencing.

Next task: continue with broader pager/VFS transaction application or another distinct master-journal durability edge that applies writes; avoid another standalone cache wrapper unless it adds a new source transition or durable-write rule.

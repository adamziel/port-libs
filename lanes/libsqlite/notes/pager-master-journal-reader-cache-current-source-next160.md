# Pager master-journal reader-cache current-source next160

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next160`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext160Plan`. It models the pager read-side edge where a connection has cached reader pages and cached master-journal membership, but must re-read the current master-journal bytes before serving the next reader after recovery. Reader cache entries are retained only when master-journal membership, source id, epoch, and page digest match the current recovered source. Dirty, pinned stale-member, stale-token, stale-epoch, absent, and same-token image-mismatched reader pages are invalidated and the next read falls back to the current recovered page image.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next160.php` covers a copied `wp_options` reader that keeps the schema page cache but rejects stale `active_plugins` and dirty plugin settings cache before serving current master-journal recovered pages.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext160Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext160Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next160.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext160Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next160.php`
- `git diff --check -- lanes/libsqlite`

Dashboard delta: `phpPass` moves from `70891` to `70958` from 67 newly passing focused PASS lines in this isolated worktree. Mapped upstream coverage remains `608 / 1589`; this is focused pager/master-journal reader-cache behavior over existing journal/cache inventory rather than a fresh upstream denominator row.

Non-overlap: avoids accepted master-journal cache recovery next122, statement-journal savepoint master next123, master-journal hot cache next136, hot-journal savepoint cache next157, cache-spill/master savepoint slices, rollback-journal apply/commit/super-journal clusters, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, and B-tree/JSON/SELECT/encoding surfaces. The new behavior is specifically reader-cache admission against the current master-journal source before next-page reads, without savepoint writes or cache-spill routing.

Dependency closure: no new support component is needed. The slice reuses lane-local master-journal member parsing and pager-cache current-source digest fencing.

Next task: continue with broader pager/VFS transaction application or another distinct master-journal durability edge that applies writes; avoid another standalone cache wrapper unless it adds a new current-source or durable-write rule.

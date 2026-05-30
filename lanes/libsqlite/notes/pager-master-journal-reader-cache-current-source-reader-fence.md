# Pager master-journal reader-cache current-source-reader-fence

Status: production suffix cleanup for `pager-master-journal-reader-cache-current-source-reader-fence`.

This slice renames the numbered production reader-cache entry to `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::currentSourceReaderFence()` and migrates the direct test/example filenames and call sites. The behavior still models the pager read-side edge where a connection has cached reader pages and cached master-journal membership, but must re-read the current master-journal bytes before serving the next reader after recovery. Reader cache entries are retained only when master-journal membership, source id, epoch, and page digest match the current recovered source. Dirty, pinned stale-member, stale-token, stale-epoch, absent, and same-token image-mismatched reader pages are invalidated and the next read falls back to the current recovered page image.

Application smoke: `application-pager-master-journal-reader-cache-current-source-reader-fence.php` covers a copied `wp_options` reader that keeps the schema page cache but rejects stale `active_plugins` and dirty plugin settings cache before serving current master-journal recovered pages.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceReaderFenceTest.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-reader-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceReaderFenceTest.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-reader-fence.php`
- `git diff --check -- lanes/libsqlite`

Dashboard delta: no `phpPass` or mapped-coverage movement; this is consolidation-only. The old dependency marker is preserved alongside the descriptive marker so dependent tests that assert the accepted metadata remain valid.

Non-overlap: avoids accepted master-journal cache recovery next122, statement-journal savepoint master next123, master-journal hot cache next136, hot-journal savepoint cache next157, cache-spill/master savepoint slices, rollback-journal apply/commit/super-journal clusters, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, and B-tree/JSON/SELECT/encoding surfaces. The new behavior is specifically reader-cache admission against the current master-journal source before next-page reads, without savepoint writes or cache-spill routing.

Dependency closure: no new support component is needed. The slice reuses lane-local master-journal member parsing and pager-cache current-source digest fencing.

Next task: continue with broader pager/VFS transaction application or another distinct master-journal durability edge that applies writes; avoid another standalone cache wrapper unless it adds a new current-source or durable-write rule.

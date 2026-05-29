# Pager Cache-Spill Master-Journal Current Source Next

Slice: `pager-cache-spill-master-journal-current-source-next`.

Behavior added:
- Re-read the current master journal before admitting dirty pager-cache pages to cache spill.
- Reject stale cached master-journal membership, wrong attached journal membership, stale source id/epoch, pinned pages, clean pages, missing rollback sources, and before-images that no longer match the current database source.
- Reuse `SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext()` for rollback-journal and WAL spill routing after the current-source filter.

Focused evidence:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerCacheSpillMasterJournalCurrentSourceNextTest.php`
- Result: `1 test files, 92 assertions, 0 failures` with 92 PASS lines.

WordPress smoke:
- `php lanes/libsqlite/examples/wordpress-pager-cache-spill-master-journal-current-source-next.php`
- Proves a copied `wp_options` import spills only pages whose before-images match the current master-journal source while stale and pinned cache pages defer.

Dependency closure:
- No new support component needed. The slice composes lane-local master-journal source validation with the existing native PHP dirty-page cache-spill journal-mode planner.

Non-overlap:
- Avoids accepted pager hot-journal cache-spill master next145, cache-spill savepoint/master next141, master-journal savepoint/cache next138, rollback-journal commit/apply, VFS savepoint rollback, WAL byte truncation, and WAL checkpoint transaction clusters. This slice is specifically about current master-journal source admission before cache spill.

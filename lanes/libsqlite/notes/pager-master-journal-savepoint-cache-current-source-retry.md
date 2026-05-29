# Pager master-journal savepoint cache current-source retry

Status: consolidation cleanup for `pager-master-journal-savepoint-cache-current-source-retry`.

This slice keeps `SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan`
as the canonical production class while removing the numbered public
entrypoint and helper method suffixes for the active savepoint retry behavior.
It composes the accepted master-journal hot-cache current-source rebase with
active savepoint behavior: savepoint before-images are captured from recovered
current-source pages, a failed savepoint write rolls back to those recovered
bytes, the retry statement captures its own before-images from the restored
current source, and optional RELEASE merges the retry page set without reviving
stale crashed cache entries.

WordPress smoke: `wordpress-pager-master-journal-savepoint-cache-current-source-retry.php` models a copied `wp_options` import where the current master journal refreshes a clean stale options page, invalidates a dirty plugin-cache page, rolls back the failed savepoint write, and retries option/transient writes against recovered current-source bytes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalSavepointCacheCurrentSourceRetryTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-savepoint-cache-current-source-retry.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalSavepointCacheCurrentSourceRetryTest.php`
  - `1 test files, 72 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-savepoint-cache-current-source-retry.php`
  - `wordpress-pager-master-journal-savepoint-cache-current-source-retry self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: none. This is consolidation-only cleanup of numbered
production method/test/example names and preserves the existing focused
assertion coverage.

Non-overlap: this avoids accepted master-journal hot-cache next136, savepoint cache current-source, master-journal cache recovery next122, cache-spill next132, rollback-journal apply/commit, super-journal commits, VFS writer/sync/lock clusters, WAL byte truncation/checkpoint/restart/truncate visibility, B-tree, JSON, SELECT, and encoding surfaces. The added behavior is specifically the active savepoint and retry-statement transition after current master-journal hot-cache rebasing.

Dependency closure: no new support component is needed. The slice reuses lane-local master-journal hot-cache rebasing and native savepoint before-image/current-source modeling.

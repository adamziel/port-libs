# Pager master-journal savepoint cache current-source next138

Status: focused PHP behavior growth for `pager-master-journal-savepoint-cache-current-source-next138`.

This slice adds `SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan`. It composes the accepted master-journal hot-cache current-source rebase with active savepoint behavior: savepoint before-images are captured from recovered current-source pages, a failed savepoint write rolls back to those recovered bytes, the retry statement captures its own before-images from the restored current source, and optional RELEASE merges the retry page set without reviving stale crashed cache entries.

WordPress smoke: `wordpress-pager-master-journal-savepoint-cache-current-source-next138.php` models a copied `wp_options` import where the current master journal refreshes a clean stale options page, invalidates a dirty plugin-cache page, rolls back the failed savepoint write, and retries option/transient writes against recovered current-source bytes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalSavepointCacheCurrentSourceNext138Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-savepoint-cache-current-source-next138.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalSavepointCacheCurrentSourceNext138Test.php`
  - `1 test files, 72 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-savepoint-cache-current-source-next138.php`
  - `wordpress-pager-master-journal-savepoint-cache-current-source-next138 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `59517` to `59589` from 72 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is focused pager behavior over existing master-journal/savepoint/cache inventory rather than a fresh manifest-backed upstream row.

Non-overlap: this avoids accepted master-journal hot-cache next136, savepoint cache next125, master-journal cache recovery next122, cache-spill next132, rollback-journal apply/commit, super-journal commits, VFS writer/sync/lock clusters, WAL byte truncation/checkpoint/restart/truncate visibility, B-tree, JSON, SELECT, and encoding surfaces. The added behavior is specifically the active savepoint and retry-statement transition after current master-journal hot-cache rebasing.

Dependency closure: no new support component is needed. The slice reuses lane-local master-journal hot-cache rebasing and native savepoint before-image/current-source modeling.

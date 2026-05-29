# Pager master-journal hot cache current-source next136

Status: focused PHP behavior growth for `pager-master-journal-hot-cache-current-source-next136`.

This slice adds `SQLitePagerMasterJournalHotCacheCurrentSourceNextPlan`. It models the pager boundary after current master-journal hot recovery where cached pager pages are retained only when their source token, epoch, and image match the recovered current source. Clean stale cache pages can be refreshed, dirty crash pages and pinned stale reader pages are invalidated, and the next read/write captures before-images from the recovered current source rather than stale cached bytes.

WordPress smoke: `wordpress-pager-master-journal-hot-cache-current-source-next136.php` covers copied `wp_options` repair behavior where a stale clean options page is refreshed from current hot recovery, a dirty plugin-settings cache page is invalidated, and the next plugin-setting write journals the recovered current-source page.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalHotCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalHotCacheCurrentSourceNext136Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-hot-cache-current-source-next136.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalHotCacheCurrentSourceNext136Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-hot-cache-current-source-next136.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `57457` to `57547` from 90 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is focused pager behavior over existing master-journal/cache inventory rather than a fresh upstream denominator row.

Non-overlap: this avoids accepted master-journal cache recovery next122, savepoint cache next125, cache-spill next132, pager savepoint WAL cache recovery, rollback-journal apply/commit, super-journal commit, WAL checkpoint/restart/truncate visibility, VFS savepoint rollback, VFS writer/sync/lock clusters, and B-tree/JSON/SELECT/encoding surfaces. The new behavior is specifically hot pager-cache source-token rebasing before the next read/write after current master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager cache and master-journal current-source primitives.

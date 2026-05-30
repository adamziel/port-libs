# Pager savepoint cache-spill hot-journal current-source next151

Status: focused PHP behavior growth for `pager-savepoint-cache-spill-hot-journal-current-source-next151`.

This slice adds `SQLitePagerSavepointCacheSpillHotJournalCurrentSourceNextPlan`. It models the current-source boundary after hot rollback-journal recovery where dirty cache pages under a savepoint may spill only when the savepoint before-image was captured from the recovered current database image. Pages with stale pre-recovery savepoint images, stale current images, pinned cache entries, clean cache entries, or missing savepoint images are deferred until they are re-journaled.

Application smoke: `application-pager-savepoint-cache-spill-hot-journal-current-source-next151.php` covers copied `wp_options` import repair where hot-journal recovery updates the current source, `active_plugins` can spill, an autoload index page with a stale savepoint before-image is blocked, and a pinned plugin-settings page remains in cache.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerSavepointCacheSpillHotJournalCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerSavepointCacheSpillHotJournalCurrentSourceNext151Test.php`
- `php -l lanes/libsqlite/examples/application-pager-savepoint-cache-spill-hot-journal-current-source-next151.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointCacheSpillHotJournalCurrentSourceNext151Test.php`
- `php lanes/libsqlite/examples/application-pager-savepoint-cache-spill-hot-journal-current-source-next151.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `67368` to `67463` from 95 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is focused pager/savepoint/hot-journal behavior over existing mapped pager inventory rather than a fresh upstream denominator row.

Non-overlap: this avoids accepted pager savepoint cache-spill next137, pager hot-journal cache-spill next127, master-current hot cache spill next145, master-journal cache/savepoint clusters, VFS savepoint rollback/write/sync/lock application, rollback-journal commit/apply, super-journal commits, WAL checkpoint/restart/truncate/savepoint clusters, B-tree, JSON, SQL executor, and encoding clusters. The new surface is specifically savepoint before-image admission after hot-journal recovery and before cache spill.

Dependency closure: no new support component is needed. The slice reuses native PHP hot rollback-journal recovery modeling, savepoint page-image rollback state, and dirty-page cache-spill journal-mode routing.

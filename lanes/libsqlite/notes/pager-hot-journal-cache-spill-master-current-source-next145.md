# Pager hot-journal cache-spill master current-source next145

Status: focused PHP behavior growth for `pager-hot-journal-cache-spill-master-current-source-next145`.

This slice adds `SQLitePagerHotJournalCacheSpillMasterCurrentSourceNextPlan`. It models the pager boundary after master-journal hot recovery where dirty cache-spill admission must use the recovered current source. Pages are admitted only when they are dirty, rollback-journal/WAL backed, unpinned, on the current master-source id/epoch, and their current image matches the hot-journal-recovered page. Stale, pinned, clean, unjournaled, and old-source cache pages are deferred before they can spill stale bytes into the database or WAL.

Application smoke: `application-pager-hot-journal-cache-spill-master-current-source-next145.php` covers copied `wp_options` recovery where only the active_plugins page can spill to a WAL frame after master hot recovery; a stale autoload page and a pinned transient cache page are deferred.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalCacheSpillMasterCurrentSourceNext145Test.php`
  - `1 test files, 92 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLitePagerHotJournalCacheSpillMasterCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerHotJournalCacheSpillMasterCurrentSourceNext145Test.php`
- `php -l lanes/libsqlite/examples/application-pager-hot-journal-cache-spill-master-current-source-next145.php`
- `php lanes/libsqlite/examples/application-pager-hot-journal-cache-spill-master-current-source-next145.php`

Expected dashboard delta: `phpPass` moves from `64226` to `64318` from 92 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is focused pager/cache-spill behavior over existing hot-journal/master-journal inventory rather than a fresh manifest row.

Non-overlap: this avoids accepted master-journal hot-cache next136, cache-spill savepoint next137, pager savepoint hot-journal master next142, WAL hot-journal checkpoint/savepoint/truncate next138/141, rollback-journal apply/commit, VFS writer/sync/lock clusters, and cache-spill journal-mode next107. The new surface is specifically dirty pager-cache spill admission after current master hot-journal recovery.

Dependency closure: no new support component is needed. The slice composes lane-local master-journal member validation and the existing pager dirty-page cache-spill journal-mode planner.

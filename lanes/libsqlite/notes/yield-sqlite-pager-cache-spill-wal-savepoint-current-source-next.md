## pager-cache-spill-wal-savepoint-current-source-next143

- Behavior: adds `SQLitePagerCacheSpillWalSavepointCurrentSourceNextPlan` for WAL-mode cache spill admission after `ROLLBACK TO` a savepoint. The plan rolls back to the retained WAL frame prefix, rejects cache pages from discarded WAL tail frames, rejects stale current-source images, requires savepoint before-images, and routes admitted dirty pages to new WAL frames rather than database pages.
- WordPress path: `examples/wordpress-pager-cache-spill-wal-savepoint-current-source-next143.php` models a plugin settings retry where `wp_options` and `active_plugins` pages spill after a savepoint rollback while a discarded autoload-index WAL tail page is kept out of the spill set.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerCacheSpillWalSavepointCurrentSourceNext143Test.php` passed `1 test files, 79 assertions, 0 failures`.
- Example evidence: `php lanes/libsqlite/examples/wordpress-pager-cache-spill-wal-savepoint-current-source-next143.php --self-test` passed.
- Non-overlap: avoids accepted WAL byte truncation, VFS savepoint rollback application, WAL checkpoint transactions, pager master-journal/hot-journal cache-spill clusters, and pager savepoint current-source next137 by covering the WAL-mode spill admission/routing step after rollback-to-savepoint.
- Dependency closure: no new support component needed; this reuses `SQLiteSavepointStack` WAL rollback/image plans and `SQLitePagerDirtyPageCacheSpillPlan` WAL frame routing.

# Pager cache-spill savepoint current-source next137

Status: focused PHP behavior growth for pager cache-spill admission while a savepoint is open.

This slice adds `SQLitePagerCacheSpillSavepointCurrentSourceNextPlan`. It filters dirty cache pages before delegating to the existing journal-mode cache-spill planner. A page is admitted only when it is dirty, unpinned, matches the current database source image, and has a savepoint before-image available for `ROLLBACK TO`. Stale current-source pages and pages without savepoint images are deferred so a cache spill cannot make an active WordPress import savepoint unrecoverable.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerCacheSpillSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerCacheSpillSavepointCurrentSourceNext137Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-cache-spill-savepoint-current-source-next137.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerCacheSpillSavepointCurrentSourceNext137Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-cache-spill-savepoint-current-source-next137.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass` +67 from the 67 independent PASS lines in `SQLitePagerCacheSpillSavepointCurrentSourceNext137Test.php`; mapped upstream coverage remains conservative because this is focused PHP pager/savepoint behavior over already mapped pager inventory.

Non-overlap: avoids accepted pager cache-spill journal modes next107, master-journal cache-spill savepoint current-source next114, cache-spill savepoint recovery next120, master/hot-journal cache-spill recovery, WAL byte truncation, VFS savepoint rollback application, rollback-journal apply/commit, VFS writer/sync/lock clusters, and WAL checkpoint transaction work. The new surface is the pre-spill savepoint admission gate for current-source and before-image availability.

Dependency closure: no new support component is needed. The patch reuses lane-local savepoint image tracking and pager dirty-page cache-spill journal-mode routing.

Next: continue with broader pager/VFS transaction application or another distinct WAL/pager durability edge; avoid another standalone cache-spill wrapper unless it applies a new durable-write or recovery invariant.

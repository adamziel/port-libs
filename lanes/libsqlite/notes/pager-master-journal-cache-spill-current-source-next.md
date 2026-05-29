# Pager master-journal cache-spill current-source next132

Status: focused PHP behavior growth for pager dirty-cache spill after master-journal recovery.

This slice adds `SQLitePagerMasterJournalCacheSpillCurrentSourceNextPlan`. It models the SQLite pager edge where a hot master journal recovers the current database source before dirty cache pages are spilled. Pages whose rollback before-image matches the master-recovered current source may be refreshed and spilled; pages whose before-image predates recovery are deferred so a cache spill cannot overwrite recovered WordPress option pages with stale bytes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalCacheSpillCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalCacheSpillCurrentSourceNext132Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-cache-spill-current-source-next132.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalCacheSpillCurrentSourceNext132Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-cache-spill-current-source-next132.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass` +86 from the 86 independent PASS lines in `SQLitePagerMasterJournalCacheSpillCurrentSourceNext132Test.php`; mapped upstream coverage remains conservative because this is focused PHP pager behavior over already mapped pager/master-journal/cache-spill inventory.

Non-overlap: avoids accepted pager cache-spill journal modes next107, pager master-journal WAL-cache next129, rollback-journal apply/commit, VFS savepoint rollback, WAL checkpoint/savepoint byte-truncation, master-journal hot rollback current source, and accepted B-tree/JSON/SQL/encoding clusters. The new surface is the cache-spill safety gate after master-journal recovery establishes the current source.

Dependency closure: no new support component is needed. The patch reuses lane-local master-journal recovery, dirty-page cache-spill, and VFS writer planning primitives.

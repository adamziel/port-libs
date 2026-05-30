# pager-master-journal-cache-current-next77

Status: focused PHP behavior growth for hot master-journal cache current/next invalidation.

This slice adds `SQLitePagerMasterJournalCacheCurrentNextPlan::currentNext()`. It models the pager cache edge where a current opener has cached master/super-journal membership, recovery changes or deletes that master-journal, and the next opener must recheck named rollback journals instead of reusing stale hot/non-hot decisions.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalCacheCurrentNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalCacheCurrentNext77Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-cache-current-next77.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalCacheCurrentNext77Test.php`
  - `1 test files, 64 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-cache-current-next77.php --self-test`
  - `application-pager-master-journal-cache-current-next77 self-test passed`

Expected dashboard movement: `phpPass` +64, from 28917 to 28981. `benchmarkDenominator.mapped` is unchanged because this is focused native pager behavior coverage, not a newly mapped upstream inventory unit.

Non-overlap: this avoids accepted hot rollback-journal application, batch70 hot super-journal recovery/apply, batch73 master/super-journal WAL recovery, super-journal commit, rollback-journal commit/apply, dirty-page cache spilling, WAL checkpoint/readmark/savepoint clusters, VFS writer/sync/lock clusters, and B-tree/JSON/SELECT current-next surfaces. The new behavior is only cached master-journal membership invalidation and per-journal hot-state recheck across current/next opener boundaries.

Dependency closure: no new support component is needed. The slice reuses lane-local rollback-journal hot-candidate parsing and existing super/master-journal recovery semantics.

Next task: connect this cache invalidation to broader pager open/recovery orchestration if a later slice applies hot-journal recovery from a persistent pager cache.

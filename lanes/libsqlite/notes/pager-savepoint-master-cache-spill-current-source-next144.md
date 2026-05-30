# Pager Savepoint Master Cache Spill Current Source Next144

## Behavior

Adds `SQLitePagerSavepointMasterCacheSpillCurrentSourceNextPlan` for the pager edge where a master-journal recovery establishes the current database source before a savepoint transaction is allowed to spill dirty cache pages. The plan only spills dirty pages whose savepoint before-images match the current source id/epoch, blocks dirty pages without a savepoint before-image, and reports rollback-to reads that restore the recovered before-image even after the spill wrote the dirty page.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePagerSavepointMasterCacheSpillCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerSavepointMasterCacheSpillCurrentSourceNext144Test.php`
- `php -l lanes/libsqlite/examples/application-pager-savepoint-master-cache-spill-current-source-next144.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointMasterCacheSpillCurrentSourceNext144Test.php`
  - `1 test files, 81 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-savepoint-master-cache-spill-current-source-next144.php`
  - emits `pager-savepoint-master-cache-spill-current-source-next144` with spilled pages `[2,3]` and rollback restored flags `[true,true]`

## Non-Overlap

This does not repeat accepted WAL byte truncation, VFS savepoint rollback application, rollback-journal apply/commit, master-journal savepoint cache next138, or pager cache-spill next140 behavior. The new slice composes the missing guard between master-journal current-source recovery, savepoint before-image ownership, and cache-spill admission.

## Dependency Closure

No new support component is needed. The implementation reuses existing `SQLitePagerDirtyPageCacheSpillPlan` and lane-local pager/savepoint current-source conventions.

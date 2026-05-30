# Pager Dirty-Page Cache Spill Current/Next71

## Scope

This slice adds bounded pager dirty-page cache-spill planning for the current/next71 path. It models the SQLite pager rule that a dirty page can be spilled from cache to the database image before commit only when:

- cache spill is enabled and the cache is at or above the spill threshold;
- the page is dirty, already present in the rollback journal, and not pinned;
- the rollback journal has been synced;
- the pager can hold or promote to an exclusive lock.

The planner keeps the write transaction open after spilling, records remaining dirty pages, and marks the database image as containing spilled dirty pages while preserving rollback-journal dependency for later rollback.

## Non-Overlap

This is narrower than accepted rollback-journal commit/apply, VFS file writer, VFS savepoint rollback, WAL byte truncation, pager savepoint current/next, and the older `SQLitePagerTransactionStatePlan` dirty/spilled page counters. It does not write database bytes, checkpoint WAL bytes, apply rollback journals, or repeat accepted VFS lock-state/process-lock behavior.

## Verification

Local verification in this lane:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerDirtyPageCacheSpillCurrentNext71Test.php`
  - `1 test files, 59 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-dirty-page-cache-spill-current-next71.php`
  - `application-pager-dirty-page-cache-spill-current-next71 self-test passed`
- `php -l lanes/libsqlite/src/SQLitePagerDirtyPageCacheSpillPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerDirtyPageCacheSpillCurrentNext71Test.php`
- `php -l lanes/libsqlite/examples/application-pager-dirty-page-cache-spill-current-next71.php`
  - all changed PHP files reported no syntax errors
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Dependency Closure

No new support component is needed. The slice composes existing native PHP pager, rollback-journal, and VFS lock/write concepts into a bounded state plan.

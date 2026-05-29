# Pager Master-Journal Reader Cache Current Source Next203

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`, a narrow
reader-cache fence for master-journal member order. It handles the case where
the same attached rollback journals still have matching token/header maps, but
the current master journal lists those members in a different order. SQLite's
master-journal recovery reads the complete ordered member list before cache
reuse; a stale reader ticket from a previous order is rejected and the affected
reader is reopened.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext203Test.php`
  - `1 test files, 50 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next203.php --self-test`
  - `wordpress-pager-master-journal-reader-cache-current-source-next203 self-test passed`
- PHP lint for changed PHP files.
  - `No syntax errors detected` for the new plan, test, and example.
- `git diff --check -- lanes/libsqlite`
  - passed with no output.

## Non-Overlap

This slice does not repeat next196 member journal header digests, next192
member token maps, next187 complete membership admission, or accepted
super-journal commit/apply behavior. It covers ordered membership as a distinct
cache-reuse source fence.

## Dependency Closure

No new support component is needed. The slice reuses lane-local pager
master-journal recovery and reader-cache current-source primitives.

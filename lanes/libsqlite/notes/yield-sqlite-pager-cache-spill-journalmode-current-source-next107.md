# SQLite pager cache-spill journal-mode current-source next107

## Behavior

- Added `SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext()` for journal-mode-specific dirty-page cache spill routing.
- Rollback-journal modes (`DELETE`, `TRUNCATE`, `PERSIST`) keep the accepted journal-sync and exclusive-lock path before writing database pages.
- `WAL` spills append WAL frames and leaves the database image unchanged until checkpoint.
- `MEMORY` uses memory-journal evidence for dirty cache pages.
- `OFF` defers spill because there is no rollback source for pre-commit database page writes.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerCacheSpillJournalModeCurrentSourceNext107Test.php`
  - `1 test files, 60 assertions, 0 failures`
  - `60` PASS lines
- `php -l lanes/libsqlite/src/SQLitePagerDirtyPageCacheSpillPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerCacheSpillJournalModeCurrentSourceNext107Test.php`
- `php -l lanes/libsqlite/examples/application-pager-cache-spill-journalmode-current-source-next107.php`
- `php lanes/libsqlite/examples/application-pager-cache-spill-journalmode-current-source-next107.php`
  - `application-pager-cache-spill-journalmode-current-source-next107 self-test passed`

## Non-overlap

This slice extends the accepted batch71 dirty-page cache-spill planner without repeating accepted hot-journal recovery, statement-journal savepoint recovery, WAL checkpoint/restart/truncate reader visibility, WAL byte truncation, rollback-journal commit/apply, VFS file writer, or journal-mode PRAGMA switching work.

## Dependency closure

No new support component is needed. The patch reuses the native PHP pager dirty-page cache-spill planner and records journal-mode-specific WAL versus rollback-journal routing for later pager/VFS transaction application.

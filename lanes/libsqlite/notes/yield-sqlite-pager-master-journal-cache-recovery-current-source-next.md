# Pager master-journal cache recovery current-source next122

## Behavior

Adds `SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan` for the
pager recovery edge where a connection has cached an older master-journal member
list, then recovery must re-read the current VFS master-journal bytes before
hot rollback and savepoint retry before-images are computed.

The plan compares cached versus current master-journal membership, discards
stale cached recovery state, runs master-journal savepoint recovery from the
current source, and reports the retry rollback preview that proves savepoint
before-images come from recovered current database bytes instead of crashed or
stale cached pages.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalCacheRecoveryCurrentSourceNext122Test.php`
  - `1 test files, 62 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-cache-recovery-current-source-next122.php`
  - reports `master_journal_cache_recovery_current_source_next122` with stale
    cache rejection and two recovered attached databases.
- PHP lint and `git diff --check -- lanes/libsqlite` were run for the changed
  lane files.

## Dependency Closure

No new support component is required. This reuses the existing native PHP
rollback-journal parser, master-journal cache planner, and savepoint
current-source recovery planner.

## Non-Overlap

Avoids accepted pager master-journal savepoint current-source next108 and
cache-spill savepoint current-source next114 by covering the narrower stale
master-journal cache recovery edge: cached membership is explicitly rejected in
favor of the current VFS master-journal bytes before retry/savepoint recovery.

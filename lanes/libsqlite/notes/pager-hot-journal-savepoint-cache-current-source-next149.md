# Pager Hot-Journal Savepoint Cache Current Source Next149

## Scope

This slice adds `SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan`, a bounded current-source model for the pager path where hot rollback-journal recovery is followed by `ROLLBACK TO` savepoint and a retrying next statement.

The behavior is intentionally narrower than accepted cache-spill/master-journal work: it focuses on page-cache source-token validity after hot recovery and savepoint rollback, before the next statement captures before-images.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalSavepointCacheCurrentSourceNext149Test.php`
- Example smoke: `php lanes/libsqlite/examples/application-pager-hot-journal-savepoint-cache-current-source-next149.php`
- PHP syntax checks and `git diff --check -- lanes/libsqlite` are required before handoff.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP pager cache source-token modeling, hot-journal recovery concepts, and savepoint page-image rollback behavior.

## Non-Overlap

Avoided accepted pager hot-journal cache-spill master behavior, WAL reader checkpoint restart, VFS rollback/savepoint writer application, master-journal cache-spill, and prior next100/next131 hot-journal savepoint cache retagging. This next149 path adds the next-statement before-image capture gate after hot recovery plus savepoint rollback refreshes the cache.

# SQLite pager master-journal statement recovery current/next80

## Behavior

Adds `SQLitePagerStatementRecoveryPlan` and VFS application for statement-journal rollback across attached databases gated by a master/super journal membership list. The plan restores only failed statement page preimages, deletes statement journals, preserves the outer rollback journals and master journal, and reports current/next page prefixes for Application import diagnostics.

## Focused evidence

- `php -l lanes/libsqlite/src/SQLitePagerStatementRecoveryPlan.php && php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php && php -l lanes/libsqlite/tests/SQLiteHeaderTest.php && php -l lanes/libsqlite/examples/application-pager-master-journal-statement-recovery-current-next80.php`: all changed PHP files reported no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php`: `Focused test run: 1 selected test files (root lock skipped)`; includes new `PASS recovers sqlite statement journals gated by attached master journals current next`; final `1 test files, 9834 assertions, 0 failures`.
- New assertion delta: `86` added `TestRunner` assertions in the focused pager80 test block; `phpPass` delta is `+1` PASS line.
- `php lanes/libsqlite/examples/application-pager-master-journal-statement-recovery-current-next80.php`: self-test passed and reported 2 recovered databases, 2 deleted statement journals, preserved master journal, preserved outer journals, and deleted statement journals.
- `git diff --check -- lanes/libsqlite`: passed with no output.

## Non-overlap

This avoids accepted hot rollback-journal recovery, super-journal commit, master/super hot-journal WAL recovery, WAL byte truncation, rollback-journal commit, VFS sync/apply, and savepoint rollback clusters. The new behavior is statement-journal recovery inside an attached transaction while the master journal and outer rollback journals remain active.

## Dependency closure

No new support component is needed. The slice reuses existing bounded VFS write/truncate/delete/sync application and adds a pager-level statement recovery planner under `lanes/libsqlite/src`.

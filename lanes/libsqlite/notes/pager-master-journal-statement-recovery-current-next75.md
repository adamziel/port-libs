# Pager Master-Journal Statement Recovery Current/Next75

## Behavior

Adds `SQLitePagerMasterJournalStatementRecoveryPlan` for the pager edge where
attached databases are first recovered from a hot rollback journal guarded by a
master/super journal, then the primary database rolls back the failing
statement subjournal and opens the next retry statement. This is intentionally
separate from accepted super-journal commit and hot-journal recovery-only
clusters: it models the statement-level subjournal recovery that occurs after
master-journal recovery has restored the current database image.

The VFS writer now has `applyMasterJournalStatementRecovery()` to apply the
combined operation sequence atomically through bounded native PHP file handles.

## Verification

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalStatementRecoveryPlan.php`
- `php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalStatementRecoveryCurrentNext75Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-statement-recovery-current-next75.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalStatementRecoveryCurrentNext75Test.php`
  - `1 test files, 64 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-statement-recovery-current-next75.php`
  - `application-pager-master-journal-statement-recovery-current-next75 self-test passed`

## Non-Overlap

Avoids accepted/queued pager hot-journal recovery-only, super-journal commit,
rollback-journal apply/commit, WAL byte truncation, savepoint image rollback,
and current `pagerj74` master-journal recovery surfaces. This slice covers the
post-master-recovery statement subjournal rollback/retry transition.

## Dependency Closure

No new support component is needed. The implementation reuses existing bounded
rollback-journal parsing, super-journal recovery planning, savepoint stack
statement-journal state, and VFS file-handle application.

# Row-Value Savepoint Numbered Method Consolidation

This pass removes the remaining numbered public `SQLiteSavepointStack` method names used by the focused row-value/savepoint pager helpers:

- `rollbackToCurrentAndRecordNextWalFrame64()` -> `rollbackToCurrentAndRecordWalFrame()`
- `rollbackToCurrentAndBeginNextStatementJournal66()` -> `rollbackToCurrentAndBeginStatementJournal()`
- `rollbackReleaseAndBeginNextSavepoint68()` -> `rollbackReleaseAndBeginSavepoint()`
- `rollbackToCurrentAndOpenNextSavepoint69()` -> `rollbackToCurrentAndOpenSavepoint()`
- `releaseCurrentSourceAndBeginNextStatementJournal90()` -> `releaseCurrentSourceAndBeginStatementJournal()`
- `releaseCurrentWalSourceAndAppendNextFrame110()` -> `releaseCurrentWalSourceAndAppendFrame()`
- `rollbackToCurrentSourceThenRelease116()` -> `rollbackToCurrentSourceThenRelease()`

Direct production caller `SQLiteVfsFileWriter`, focused tests, and WordPress savepoint examples now call the stable unsuffixed methods. No numbered compatibility methods were left behind.

Verification:

- `php -l` passed for changed PHP source and focused tests.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointCurrentNext64Test.php lanes/libsqlite/tests/SQLitePagerSavepointStatementJournalCurrentNext66Test.php lanes/libsqlite/tests/SQLitePagerSavepointReleaseNextCurrentNext68Test.php lanes/libsqlite/tests/SQLitePagerSavepointCurrentNext69Test.php lanes/libsqlite/tests/SQLitePagerStatementJournalSavepointCurrentSourceNext90Test.php lanes/libsqlite/tests/SQLiteTransactionSavepointWalReleaseCurrentSourceNext110Test.php lanes/libsqlite/tests/SQLiteSavepointNestedRollbackReleaseCurrentSourceNext116Test.php`
  - `7 test files, 400 assertions, 0 failures`
- Changed WordPress examples:
  - `php lanes/libsqlite/examples/wordpress-pager-savepoint-current-next64.php --self-test`
  - `php lanes/libsqlite/examples/wordpress-pager-savepoint-statement-current-next66.php --self-test`
  - `php lanes/libsqlite/examples/wordpress-pager-savepoint-release-next-current-next68.php --self-test`
  - `php lanes/libsqlite/examples/wordpress-pager-savepoint-current-next69.php --self-test`
  - `php lanes/libsqlite/examples/wordpress-pager-statement-journal-savepoint-current-source-next90.php --self-test`
  - `php lanes/libsqlite/examples/wordpress-transaction-savepoint-wal-release-current-source-next110.php --self-test`
  - `php lanes/libsqlite/examples/wordpress-savepoint-nested-rollback-release-current-source-next116.php --self-test`
- Exact removed method-name scan is clean in `src`, `tests`, and `examples`.

Dependency closure: no new support component is needed; this is a production API-name consolidation over existing native PHP savepoint, statement-journal, WAL-frame, and current-source page-image behavior.

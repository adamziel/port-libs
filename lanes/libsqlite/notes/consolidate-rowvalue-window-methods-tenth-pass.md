## Row-value/window method consolidation tenth pass

- Consolidated the remaining direct numbered 289 and 290-293
  production entrypoints in `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
  into descriptive unsuffixed methods:
  `executeReturningWindowSavepointRetry()` and
  `executeStatementPartitionedReturningWindowSavepointRetry()`.
- Renamed the associated private helpers to descriptive unsuffixed helper names.
- Migrated the direct focused tests and Application examples to descriptive
  unsuffixed filenames and method calls.
- No new support component is needed; this reuses existing row-value
  UPDATE/DELETE RETURNING execution.

Focused verification:

- `php -l` on changed PHP files: passed for the production class, two focused
  tests, and two Application examples.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueReturningWindowSavepointRetryTest.php lanes/libsqlite/tests/SQLiteRowValueStatementPartitionedReturningWindowTest.php`:
  `2 test files, 83 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-savepoint-retry.php --self-test`:
  passed.
- `php lanes/libsqlite/examples/application-rowvalue-statement-partitioned-returning-window.php --self-test`:
  passed.
- `git diff --check -- lanes/libsqlite`: passed.

# Row-Value Savepoint Method Consolidation Eleventh Pass

## Scope

Renamed two remaining numbered production entrypoints in `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan` to descriptive canonical methods:

- `executeNext193()` -> `executeFailStreamSavepoint()`
- `executeNext218()` -> `executeRollbackToSavepointCurrentSource()`

The related private helper methods were renamed to descriptive names, and the direct row-value savepoint tests/examples now call the canonical methods.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext193Test.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext218Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-fail-stream-savepoint-current-source-next193.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-rollback-to-current-source-next218.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext193Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext218Test.php`
  - `2 test files, 130 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-fail-stream-savepoint-current-source-next193.php --self-test`
  - `application-rowvalue-fail-stream-savepoint-current-source-next193 self-test passed`
- `php lanes/libsqlite/examples/application-rowvalue-rollback-to-current-source-next218.php --self-test`
  - exited `0` and printed the expected rollback scenario payload

## Dependency Closure

No new support component is needed. The examples reuse the existing `SQLiteAffinityComparison` support file already required by other row-value examples.

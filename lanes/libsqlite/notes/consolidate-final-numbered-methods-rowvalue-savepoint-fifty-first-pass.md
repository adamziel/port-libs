# Row-Value Savepoint Numbered Caller Cleanup

Consolidated the stale row-value savepoint example callers for the 161/164/178
variants onto the existing descriptive production entry points on
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan`:

- `executeConflictRetrySavepointBatch()`
- `executeNullInequalityRetrySavepointBatch()`
- `executeValuesRetrySavepointBatch()`

The three Application examples were renamed to stable unsuffixed filenames and
their public scenario/self-test labels no longer carry worker-number suffixes.
No production compatibility shims were added.

Verification:

- `php -l lanes/libsqlite/examples/application-rowvalue-fail-rollback-retry-savepoint.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-rollback-retry-savepoint.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-rollback-transaction-savepoint.php`
- `php lanes/libsqlite/examples/application-rowvalue-fail-rollback-retry-savepoint.php`
- `php lanes/libsqlite/examples/application-rowvalue-rollback-retry-savepoint.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-rollback-transaction-savepoint.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext161Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext164Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext178Test.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is consolidation of
direct callers onto the existing native PHP row-value savepoint implementation.

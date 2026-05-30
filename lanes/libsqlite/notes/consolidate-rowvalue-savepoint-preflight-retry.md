# Row-value savepoint preflight/retry consolidation

This slice removes the numbered `next158` identity from the row-value UPDATE/DELETE RETURNING savepoint preflight/retry helper family.

Changed surfaces:

- Production diagnostics in `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executePreflightRetrySavepointBatch()` now use stable descriptive wording.
- Direct focused test moved from its numbered predecessor to `SQLiteRowValueUpdateDeleteReturningSavepointPreflightRetryTest.php`.
- Direct Application smoke moved from its numbered predecessor to `application-rowvalue-update-delete-returning-savepoint-preflight-retry.php`.

Dependency closure: no new support component is needed; this is a naming consolidation over the existing native PHP row-value UPDATE/DELETE RETURNING and savepoint rollback/retry implementation.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php && php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointPreflightRetryTest.php && php -l lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-preflight-retry.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointPreflightRetryTest.php`: 1 test files, 57 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-preflight-retry.php --self-test`: self-test passed.
- `git diff --check -- lanes/libsqlite`: passed.

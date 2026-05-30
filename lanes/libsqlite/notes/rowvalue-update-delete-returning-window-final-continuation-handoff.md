# Row-value UPDATE/DELETE RETURNING window final continuation handoff

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds stable `executeStableFinalContinuationHandoff()` aliases over the final continuation range without adding a numbered production method.
- Reuses the canonical ready-publication range executor for the full dynamic tail from `next958` through `next1181`.
- Renames the direct Application smoke and focused test away from the generated numeric range while preserving the ready-seal assertions.
- Preserves observable numbered status and receipt keys while exposing stable `candidate_count` and `final_publication_step` summary fields for the complete dynamic handoff.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-final-continuation-handoff.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowFinalContinuationHandoffTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowFinalContinuationHandoffTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindow*Test.php lanes/libsqlite/tests/SQLiteRowValueChunkCursorReleaseWindowTest.php lanes/libsqlite/tests/SQLiteRowValueChunkedYieldResumeWindowTest.php lanes/libsqlite/tests/SQLiteRowValueRowReceiptAdmissionWindowTest.php lanes/libsqlite/tests/SQLiteRowValueStatementPartitionedReturningWindowTest.php lanes/libsqlite/tests/SQLiteRowValueReturningWindowSavepointRetryTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-final-continuation-handoff.php --self-test`
- `git diff --check -- lanes/libsqlite`

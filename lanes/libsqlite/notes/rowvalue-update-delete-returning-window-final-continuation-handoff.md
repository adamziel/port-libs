# Row-value UPDATE/DELETE RETURNING window final continuation handoff

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds stable `executeStableFinalContinuationHandoff()` aliases over the final continuation range without adding a numbered production method.
- Reuses the canonical ready-publication range executor for the direct successor blocks after the prior ready seal.
- Renames the direct WordPress smoke and focused test away from the generated numeric range while preserving the ready-seal assertions.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-final-continuation-handoff.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowFinalContinuationHandoffTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowFinalContinuationHandoffTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-final-continuation-handoff.php --self-test`
- `git diff --check -- lanes/libsqlite`

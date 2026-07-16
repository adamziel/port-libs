# Row-value UPDATE/DELETE RETURNING Window Ready-Publication Final Continuation

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Keeps the final ready-publication continuation on the existing canonical range executor instead of exposing a numbered test/example contract.
- Renames the direct Application smoke and focused test to stable descriptive names.
- Validates that the final continuation consumes the prior ready seal and publishes the handoff, source-audit, throughput-preflight, and ready-seal phases.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-ready-publication-final-continuation.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationFinalContinuationTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationFinalContinuationTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-ready-publication-final-continuation.php --self-test`
- `git diff --check -- lanes/libsqlite`

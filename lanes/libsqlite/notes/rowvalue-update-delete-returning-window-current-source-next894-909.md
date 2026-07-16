# Row-value UPDATE/DELETE RETURNING window current-source next894-909

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds `executeNext894()` through `executeNext909()` on the existing consolidated plan class.
- Reuses `continuationNext382397()` for the direct successor blocks after `next893_ready`.
- Validates that next894 consumes `next893_ready` and that next897, next901, next905, and next909 publish ready seals.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next894-909.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext894909Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext878893Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext894909Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next878-893.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next894-909.php --self-test`
- `git diff --check`

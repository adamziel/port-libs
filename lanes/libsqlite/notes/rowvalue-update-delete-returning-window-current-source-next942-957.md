# Row-value UPDATE/DELETE RETURNING window current-source next942-957

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds `executeNext942()` through `executeNext957()` on the existing consolidated plan class.
- Reuses `continuationNext382397()` for the direct successor blocks after `next941_ready`.
- Validates that next942 consumes `next941_ready` and that next945, next949, next953, and next957 publish ready seals.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next942-957.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext942957Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext926941Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext942957Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next926-941.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next942-957.php --self-test`
- `git diff --check`

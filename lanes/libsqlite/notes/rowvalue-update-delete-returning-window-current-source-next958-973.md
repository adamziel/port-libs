# Row-value UPDATE/DELETE RETURNING window current-source next958-973

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds `executeNext958()` through `executeNext973()` on the existing consolidated plan class.
- Reuses `continuationNext382397()` for the direct successor blocks after `next957_ready`.
- Validates that next958 consumes `next957_ready` and that next961, next965, next969, and next973 publish ready seals.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next958-973.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext958973Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext942957Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext958973Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next942-957.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next958-973.php --self-test`
- `git diff --check`

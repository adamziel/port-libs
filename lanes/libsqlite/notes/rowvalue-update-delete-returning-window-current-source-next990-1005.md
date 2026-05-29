# Row-value UPDATE/DELETE RETURNING window current-source next990-1005

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds `executeNext990()` through `executeNext1005()` on the existing consolidated plan class.
- Reuses `continuationNext382397()` for the direct successor blocks after `next989_ready`.
- Validates that next990 consumes `next989_ready` and that next993, next997, next1001, and next1005 publish ready seals.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next990-1005.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext9901005Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext974989Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext9901005Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next974-989.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next990-1005.php --self-test`
- `git diff --check`

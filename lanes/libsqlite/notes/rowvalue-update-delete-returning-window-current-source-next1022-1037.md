# Row-value UPDATE/DELETE RETURNING window current-source next1022-1037

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds `executeNext1022()` through `executeNext1037()` on the existing consolidated plan class.
- Reuses `continuationNext382397()` for the direct successor blocks after `next1021_ready`.
- Validates that next1022 consumes `next1021_ready` and that next1025, next1029, next1033, and next1037 publish ready seals.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1022-1037.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10221037Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10061021Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10221037Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1006-1021.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1022-1037.php --self-test`
- `git diff --check`

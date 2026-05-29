# Row-value UPDATE/DELETE RETURNING window current-source next1054-1069

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds `executeNext1054()` through `executeNext1069()` on the existing consolidated plan class.
- Reuses `continuationNext382397()` for the direct successor blocks after `next1053_ready`.
- Validates that next1054 consumes `next1053_ready` and that next1057, next1061, next1065, and next1069 publish ready seals.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1054-1069.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10541069Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10381053Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10541069Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1038-1053.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1054-1069.php --self-test`
- `git diff --check`

# Row-value UPDATE/DELETE RETURNING window current-source next1070-1085

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds `executeNext1070()` through `executeNext1085()` on the existing consolidated plan class.
- Reuses `continuationNext382397()` for the direct successor blocks after `next1069_ready`.
- Validates that next1070 consumes `next1069_ready` and that next1073, next1077, next1081, and next1085 publish ready seals.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1070-1085.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10701085Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10541069Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10701085Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1054-1069.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1070-1085.php --self-test`
- `git diff --check`

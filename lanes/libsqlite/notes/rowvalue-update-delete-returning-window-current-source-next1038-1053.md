# Row-value UPDATE/DELETE RETURNING window current-source next1038-1053

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds `executeNext1038()` through `executeNext1053()` on the existing consolidated plan class.
- Reuses `continuationNext382397()` for the direct successor blocks after `next1037_ready`.
- Validates that next1038 consumes `next1037_ready` and that next1041, next1045, next1049, and next1053 publish ready seals.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1038-1053.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10381053Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10221037Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10381053Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1022-1037.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1038-1053.php --self-test`
- `git diff --check`

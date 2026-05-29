# Row-value UPDATE/DELETE RETURNING window current-source next926-941

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds `executeNext926()` through `executeNext941()` on the existing consolidated plan class.
- Reuses `continuationNext382397()` for the direct successor blocks after `next925_ready`.
- Validates that next926 consumes `next925_ready` and that next929, next933, next937, and next941 publish ready seals.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next926-941.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext926941Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext910925Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext926941Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next910-925.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next926-941.php --self-test`
- `git diff --check`

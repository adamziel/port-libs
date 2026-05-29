# Row-value UPDATE/DELETE RETURNING window current-source next974-989

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds `executeNext974()` through `executeNext989()` on the existing consolidated plan class.
- Reuses `continuationNext382397()` for the direct successor blocks after `next973_ready`.
- Validates that next974 consumes `next973_ready` and that next977, next981, next985, and next989 publish ready seals.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next974-989.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext974989Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext958973Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext974989Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next958-973.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next974-989.php --self-test`
- `git diff --check`

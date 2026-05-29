# Row-value UPDATE/DELETE RETURNING window current-source next910-925

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds `executeNext910()` through `executeNext925()` on the existing consolidated plan class.
- Reuses `continuationNext382397()` for the direct successor blocks after `next909_ready`.
- Validates that next910 consumes `next909_ready` and that next913, next917, next921, and next925 publish ready seals.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next910-925.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext910925Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext894909Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext910925Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next894-909.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next910-925.php --self-test`
- `git diff --check`

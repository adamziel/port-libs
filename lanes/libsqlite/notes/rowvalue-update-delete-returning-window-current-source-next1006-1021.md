# Row-value UPDATE/DELETE RETURNING window current-source next1006-1021

## Summary

- Extends the existing `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` consolidated class.
- Adds `executeNext1006()` through `executeNext1021()` on the existing consolidated plan class.
- Reuses `continuationNext382397()` for the direct successor blocks after `next1005_ready`.
- Validates that next1006 consumes `next1005_ready` and that next1009, next1013, next1017, and next1021 publish ready seals.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1006-1021.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10061021Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationSealTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10061021Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-ready-publication-seal.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1006-1021.php --self-test`
- `git diff --check`

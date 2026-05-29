# Row-value UPDATE/DELETE RETURNING window current-source next1150-1165

## Scope
- Extends the existing consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` continuation pattern.
- Adds `executeNext1150()` through `executeNext1165()` as the direct continuation from `next1149_ready`.
- Keeps the domain narrowly on row-value UPDATE/DELETE RETURNING window current-source metadata; no executor, WAL/VFS, JSON, planner, B-tree, PRAGMA, trigger, or coordination file changes.
- Validates that next1150 consumes `next1149_ready` and that next1153, next1157, next1161, and next1165 publish ready seals.

## Validation
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1150-1165.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11501165Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11341149Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11501165Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1150-1165.php --self-test`
- `git diff --check`

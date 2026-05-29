# Row-value UPDATE/DELETE RETURNING window current-source next1118-1133

## Scope
- Extends the existing consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` continuation pattern.
- Adds `executeNext1118()` through `executeNext1133()` as the direct continuation from `next1117_ready`.
- Keeps the domain narrowly on row-value UPDATE/DELETE RETURNING window current-source metadata; no executor, WAL/VFS, JSON, planner, B-tree, PRAGMA, trigger, or coordination file changes.
- Validates that next1118 consumes `next1117_ready` and that next1121, next1125, next1129, and next1133 publish ready seals.

## Validation
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1118-1133.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11181133Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11021117Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11181133Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1118-1133.php --self-test`
- `git diff --check`

# Row-value UPDATE/DELETE RETURNING window current-source next1134-1149

## Scope
- Extends the existing consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` continuation pattern.
- Adds `executeNext1134()` through `executeNext1149()` as the direct continuation from `next1133_ready`.
- Keeps the domain narrowly on row-value UPDATE/DELETE RETURNING window current-source metadata; no executor, WAL/VFS, JSON, planner, B-tree, PRAGMA, trigger, or coordination file changes.
- Validates that next1134 consumes `next1133_ready` and that next1137, next1141, next1145, and next1149 publish ready seals.

## Validation
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1134-1149.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11341149Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11181133Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11341149Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1134-1149.php --self-test`
- `git diff --check`

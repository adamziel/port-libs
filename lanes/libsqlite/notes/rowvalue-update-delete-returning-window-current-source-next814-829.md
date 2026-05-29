# Row-Value UPDATE/DELETE RETURNING Window Current Source next814-829

This slice extends the consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` continuation directly after the completed next798-813 seal.

- `next814` records the direct handoff from `next813_ready`.
- `next815` audits current-source and next-source hashes for the same retry rows.
- `next816` captures throughput preflight counters.
- `next817`, `next821`, `next825`, and `next829` seal each four-step block as ready.

The matching WordPress example uses copied `wp_options` rows and row-value UPDATE/DELETE RETURNING statements only; it does not add parser, executor, WAL/VFS, planner, B-tree, PRAGMA, trigger, or coordination-file coverage.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next814-829.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext814829Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext798813Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext814829Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next814-829.php --self-test`
- `git diff --check`

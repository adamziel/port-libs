# rowvalue-update-delete-returning-window-current-source-next269-272-after-current

Status: focused PHP behavior growth for `rowvalue-update-delete-returning-window-current-source-next269-272-after-current`.

Scope: extends the row-value UPDATE/DELETE RETURNING current-source after-current handoff after next268 with deterministic closure receipts, DELETE RETURNING guards, UPDATE RETURNING fences, and final readiness summary metadata. No broad suite, WAL/VFS, JSON table, planner, B-tree, encoding, or PRAGMA behavior is changed.

WordPress smoke: `wordpress-rowvalue-returning-window-current-source-next269-272-after-current.php` models copied `wp_options` yield, rollback, retry, and mixed UPDATE/DELETE RETURNING rows using the next268 fixture.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next269-272-after-current.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext269272AfterCurrentTest.php`
- `php tools/run-tests.php libsqlite SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext269272AfterCurrentTest`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next269-272-after-current.php --self-test`
- `git diff --check`

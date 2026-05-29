# SQLite VFS current-source next530-545

This slice adds `SQLiteVfsCurrentSourceNext530545Plan` as the direct successor to merged next514-529. It requires the latest `shared-cache-next529` publish receipt before snapshotting `reader-ready-next545`, records a reuse claim, and only publishes `shared-cache-next545` when the source handle/path/owner/data version, publish count, receipt digest, and dirty-page state still match the captured snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext530545Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext530545Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next530-545.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext530545Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next530-545.php --self-test`
- `git diff --check`

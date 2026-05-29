# SQLite VFS current-source next946-961

This slice extends the consolidated `SQLiteVfsCurrentSourceNextPlan` coverage as the direct successor to integrated next930-945. It requires the latest `shared-cache-next945` publish receipt before snapshotting `reader-ready-next961`, records `reader-reuse-next961`, and publishes `shared-cache-next961` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next930-945.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next946-961.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next930-945.php --self-test`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next946-961.php --self-test`
- `git diff --check`

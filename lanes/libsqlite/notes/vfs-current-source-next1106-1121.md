## VFS current-source next1106-1121

This slice extends the consolidated `SQLiteVfsCurrentSourceNextPlan` coverage as the direct successor to integrated next1090-1105. It requires the latest `shared-cache-next1105` publish receipt before snapshotting `reader-ready-next1121`, records `reader-reuse-next1121`, and publishes `shared-cache-next1121` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next1090-1105.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next1106-1121.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next1090-1105.php --self-test`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next1106-1121.php --self-test`

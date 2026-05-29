# SQLite VFS current-source next930-945

This slice extends the consolidated `SQLiteVfsCurrentSourceNext626641Plan` coverage as the direct successor to integrated next914-929. It requires the latest `shared-cache-next929` publish receipt before snapshotting `reader-ready-next945`, records `reader-reuse-next945`, and publishes `shared-cache-next945` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext626641Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext626641Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next930-945.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext626641Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next914-929.php --self-test`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next930-945.php --self-test`

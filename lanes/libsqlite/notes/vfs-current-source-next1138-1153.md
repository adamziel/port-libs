## VFS current-source next1138-1153

This slice extends the consolidated `SQLiteVfsCurrentSourceNext626641Plan` coverage as the direct successor to integrated next1122-1137. It requires the latest `shared-cache-next1137` publish receipt before snapshotting `reader-ready-next1153`, records `reader-reuse-next1153`, and publishes `shared-cache-next1153` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext626641Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext626641Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next1122-1137.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next1138-1153.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext626641Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next1122-1137.php --self-test`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next1138-1153.php --self-test`

## VFS current-source next1154-1169

This slice extends the consolidated `SQLiteVfsCurrentSourceNextPlan` coverage as the direct successor to integrated next1138-1153. It requires the latest `shared-cache-next1153` publish receipt before snapshotting `reader-ready-next1169`, records `reader-reuse-next1169`, and publishes `shared-cache-next1169` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next1138-1153.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next1154-1169.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next1138-1153.php --self-test`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next1154-1169.php --self-test`

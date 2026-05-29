# SQLite VFS current-source next1010-1025

This slice extends the consolidated `SQLiteVfsCurrentSourceNext626641Plan` coverage as the direct successor to integrated next994-1009. It requires the latest `shared-cache-next1009` publish receipt before snapshotting `reader-ready-next1025`, records `reader-reuse-next1025`, and publishes `shared-cache-next1025` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext626641Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext626641Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next994-1009.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next1010-1025.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext626641Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next994-1009.php --self-test`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next1010-1025.php --self-test`
- `git diff --check`

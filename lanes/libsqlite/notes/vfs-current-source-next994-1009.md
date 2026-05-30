# SQLite VFS current-source next994-1009

This slice extends the consolidated `SQLiteVfsCurrentSourceNextPlan` coverage as the direct successor to integrated next978-993. It requires the latest `shared-cache-next993` publish receipt before snapshotting `reader-ready-next1009`, records `reader-reuse-next1009`, and publishes `shared-cache-next1009` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next978-993.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next994-1009.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-next978-993.php --self-test`
- `php lanes/libsqlite/examples/application-vfs-current-source-next994-1009.php --self-test`
- `git diff --check`

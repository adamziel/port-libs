# SQLite VFS current-source next978-993

This slice extends the consolidated `SQLiteVfsCurrentSourceNextPlan` coverage as the direct successor to integrated next962-977. It requires the latest `shared-cache-next977` publish receipt before snapshotting `reader-ready-next993`, records `reader-reuse-next993`, and publishes `shared-cache-next993` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next962-977.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next978-993.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next962-977.php --self-test`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next978-993.php --self-test`
- `git diff --check`

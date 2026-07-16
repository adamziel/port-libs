## VFS current-source next1090-1105

This slice extends the consolidated `SQLiteVfsCurrentSourceNextPlan` coverage as the direct successor to integrated next1074-1089. It requires the latest `shared-cache-next1089` publish receipt before snapshotting `reader-ready-next1105`, records `reader-reuse-next1105`, and publishes `shared-cache-next1105` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next1090-1105.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-next1074-1089.php --self-test`
- `php lanes/libsqlite/examples/application-vfs-current-source-next1090-1105.php --self-test`

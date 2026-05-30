# SQLite VFS current-source next1026-1041

This slice extends the consolidated `SQLiteVfsCurrentSourceNextPlan` coverage as the direct successor to integrated next1010-1025. It requires the latest `shared-cache-next1025` publish receipt before snapshotting `reader-ready-next1041`, records `reader-reuse-next1041`, and publishes `shared-cache-next1041` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next1010-1025.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next1026-1041.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-next1010-1025.php --self-test`
- `php lanes/libsqlite/examples/application-vfs-current-source-next1026-1041.php --self-test`
- `git diff --check`

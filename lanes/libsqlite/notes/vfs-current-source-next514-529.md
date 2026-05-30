# SQLite VFS current-source next514-529

This slice adds `SQLiteVfsCurrentSourceNextPlan` as the direct successor to merged next498-513. It requires the latest `shared-cache-next513` publish receipt before snapshotting `reader-ready-next529`, records a reuse claim, and only publishes `shared-cache-next529` when the source handle/path/owner/data version, publish count, receipt digest, and dirty-page state still match the captured snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next514-529.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-next514-529.php --self-test`
- `git diff --check`

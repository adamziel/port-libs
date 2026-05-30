# SQLite VFS current-source next578-593

This slice adds `SQLiteVfsCurrentSourceNextPlan` as the direct successor to merged next562-577. It requires the latest `shared-cache-next577` publish receipt before snapshotting `reader-ready-next593`, records a reuse claim, and only publishes `shared-cache-next593` when the source handle/path/owner/data version, publish count, receipt digest, and dirty-page state still match the captured snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next578-593.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-next578-593.php --self-test`
- `git diff --check`

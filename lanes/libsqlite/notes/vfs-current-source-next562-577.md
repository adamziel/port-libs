# SQLite VFS current-source next562-577

This slice adds `SQLiteVfsCurrentSourceNextPlan` as the direct successor to merged next546-561. It requires the latest `shared-cache-next561` publish receipt before snapshotting `reader-ready-next577`, records a reuse claim, and only publishes `shared-cache-next577` when the source handle/path/owner/data version, publish count, receipt digest, and dirty-page state still match the captured snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next562-577.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-next562-577.php --self-test`
- `git diff --check`

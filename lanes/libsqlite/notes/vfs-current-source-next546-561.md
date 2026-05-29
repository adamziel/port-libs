# SQLite VFS current-source next546-561

This slice adds `SQLiteVfsCurrentSourceNext546561Plan` as the direct successor to merged next530-545. It requires the latest `shared-cache-next545` publish receipt before snapshotting `reader-ready-next561`, records a reuse claim, and only publishes `shared-cache-next561` when the source handle/path/owner/data version, publish count, receipt digest, and dirty-page state still match the captured snapshot.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext546561Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext546561Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next546-561.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext546561Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next546-561.php --self-test`
- `git diff --check`

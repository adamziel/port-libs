# SQLite VFS current-source next834-849

This slice extends the consolidated `SQLiteVfsCurrentSourceNext626641Plan` coverage as the direct successor to integrated next818-833. It requires the latest `shared-cache-next833` publish receipt before snapshotting `reader-ready-next849`, records `reader-reuse-next849`, and publishes `shared-cache-next849` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

No new numbered source class is added; the existing VFS current-source plan owns the snapshot/claim/publish contract and now advertises the `vfs-current-source-snapshot-reuse-publish-next834-849` dependency marker.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext626641Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext626641Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next834-849.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext626641Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next818-833.php --self-test`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next834-849.php --self-test`
- `git diff --check`

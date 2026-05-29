# SQLite VFS current-source next770-785

This slice extends the consolidated `SQLiteVfsCurrentSourceNext626641Plan` coverage as the direct successor to integrated next754-769. It requires the latest `shared-cache-next769` publish receipt before snapshotting `reader-ready-next785`, records `reader-reuse-next785`, and publishes `shared-cache-next785` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

No new numbered source class is added; the existing VFS current-source plan owns the snapshot/claim/publish contract and now advertises the `vfs-current-source-snapshot-reuse-publish-next770-785` dependency marker.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext626641Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext626641Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next754-769.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next770-785.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext626641Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next754-769.php --self-test`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next770-785.php --self-test`
- `git diff --check`

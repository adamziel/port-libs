# SQLite VFS current-source next786-801

This slice extends the consolidated `SQLiteVfsCurrentSourceNextPlan` coverage as the direct successor to integrated next770-785. It requires the latest `shared-cache-next785` publish receipt before snapshotting `reader-ready-next801`, records `reader-reuse-next801`, and publishes `shared-cache-next801` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

No new numbered source class is added; the existing VFS current-source plan owns the snapshot/claim/publish contract and now advertises the `vfs-current-source-snapshot-reuse-publish-next786-801` dependency marker.

Focused validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next786-801.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-next770-785.php --self-test`
- `php lanes/libsqlite/examples/application-vfs-current-source-next786-801.php --self-test`
- `git diff --check`

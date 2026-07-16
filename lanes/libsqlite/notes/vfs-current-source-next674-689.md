# SQLite VFS current-source next674-689

This slice extends the consolidated `SQLiteVfsCurrentSourceNextPlan` coverage as the direct successor to integrated next658-673. It requires the latest `shared-cache-next673` publish receipt before snapshotting `reader-ready-next689`, records `reader-reuse-next689`, and publishes `shared-cache-next689` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

No new numbered source class is added; the existing VFS current-source plan owns the snapshot/claim/publish contract and now advertises the `vfs-current-source-snapshot-reuse-publish-next674-689` dependency marker.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next674-689.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-next674-689.php --self-test`

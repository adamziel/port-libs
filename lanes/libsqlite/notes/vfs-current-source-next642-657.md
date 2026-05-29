# SQLite VFS current-source next642-657

This slice extends the consolidated `SQLiteVfsCurrentSourceNextPlan` coverage as the direct successor to integrated next626-641. It requires the latest `shared-cache-next641` publish receipt before snapshotting `reader-ready-next657`, records `reader-reuse-next657`, and publishes `shared-cache-next657` only while the handle, path, owner, data version, publish count, receipt digest, and dirty-page state still match the current-source snapshot.

No new numbered source class is added; the existing VFS current-source plan already owns the snapshot/claim/publish contract and now advertises the `vfs-current-source-snapshot-reuse-publish-next642-657` dependency marker.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next642-657.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next642-657.php --self-test`
- `git diff --check`

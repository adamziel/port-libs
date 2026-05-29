# SQLite VFS current-source next594-609

This slice adds `SQLiteVfsCurrentSourceNext594609Plan` as the direct successor to merged next578-593. It requires the latest `shared-cache-next593` publish receipt before snapshotting `reader-ready-next609`, records a reuse claim, and only publishes `shared-cache-next609` when the source handle/path/owner/data version, publish count, receipt digest, and dirty-page state still match the captured snapshot.

A new numbered source class is used because the established VFS current-source classes are final per slice; this keeps the next594-609 handoff independent while preserving the canonical snapshot/claim/publish contract.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext594609Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext594609Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next594-609.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext594609Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next594-609.php --self-test`
- `git diff --check`

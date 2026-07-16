# SQLite VFS current-source next610-625

This slice adds `SQLiteVfsCurrentSourceNextPlan` as the direct successor to merged next594-609. It requires the latest `shared-cache-next609` publish receipt before snapshotting `reader-ready-next625`, records a reuse claim, and only publishes `shared-cache-next625` when the source handle/path/owner/data version, publish count, receipt digest, and dirty-page state still match the captured snapshot.

A new numbered source class is used because the established VFS current-source classes are final per slice; this keeps the next610-625 handoff independent while preserving the canonical snapshot/claim/publish contract.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next610-625.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-next610-625.php --self-test`
- `git diff --check`

# SQLite VFS current-source next298-305

Prepared as the direct follow-on to merged next290-297.

- Scope: current-source snapshot reuse planning only.
- Entry point: `PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan`.
- Starting receipt: `shared-cache-next297`.
- New snapshot/claim/publish tokens: `reader-ready-next305`, `reader-reuse-next305`, `shared-cache-next305`.
- Non-overlap: this slice only adds next298-305 artifacts and leaves previous next290-297 files, dirty flushing, VFS locking, WAL checkpointing, and B-tree behavior untouched.

Validation targets:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next298-305.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next298-305.php --self-test`

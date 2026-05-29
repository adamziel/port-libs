# SQLite VFS current-source next306-313

Prepared as the direct follow-on to merged next298-305.

- Scope: current-source snapshot reuse planning only.
- Entry point: `PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext306313Plan`.
- Starting receipt: `shared-cache-next305`.
- New snapshot/claim/publish tokens: `reader-ready-next313`, `reader-reuse-next313`, `shared-cache-next313`.
- Non-overlap: this slice only adds next306-313 artifacts and leaves previous next298-305 files, dirty flushing, VFS locking, WAL checkpointing, and B-tree behavior untouched.

Validation targets:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext306313Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext306313Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next306-313.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext306313Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next306-313.php --self-test`

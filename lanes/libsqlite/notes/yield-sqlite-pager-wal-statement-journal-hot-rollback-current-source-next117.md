# Pager WAL Statement Hot Rollback Current Source Next117

## Behavior

Adds `SQLiteVfsFileWriter::applyWalHotJournalStatementCurrentSourceNext117()` for the VFS application edge where a copied Application SQLite database has current database bytes, a hot rollback journal, and a WAL sidecar whose tail belongs to a failed statement subjournal. The writer hydrates the current source files from the VFS root, reuses the existing hot-journal/WAL statement current-source planner, and applies the resulting database, journal deletion, and WAL-prefix truncation operations atomically through native PHP file handles.

This is intentionally not a duplicate of accepted hot rollback admission, WAL byte truncation, rollback-journal commit, VFS writer, or savepoint rollback wrappers. The new behavior is the combined current-source file-handle application of hot rollback plus statement-journal rollback over a WAL sidecar.

## Focused Evidence

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerWalStatementHotRollbackCurrentSourceNext117Test.php`

Result: `1 test files, 61 assertions, 0 failures` with 48 PASS lines.

`php lanes/libsqlite/examples/application-wal-statement-hot-rollback-current-source-next117.php --self-test`

Result: `application WAL statement hot rollback current-source next117 self-test passed`.

## Dependency Closure

No new support component is needed. This reuses the native rollback-journal parser, WAL parser/checksum boundary logic, `SQLiteSavepointStack` statement-journal rollback state, and `SQLiteVfsFileWriter` atomic operation application.

## Non-Overlap

Avoids accepted rollback-journal hot recovery/application, savepoint WAL byte truncation, WAL checkpoint transaction, VFS sync/apply, VFS locked writer, super-journal commit, rollback-journal commit, and batch109-113 WAL MVCC/checkpoint reader recovery surfaces. The next117 slice applies the existing statement-current-source recovery sequence to real current-source sidecar files.

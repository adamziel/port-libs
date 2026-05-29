# Pager WAL Recovery Current

## Slice

- Added stable `SQLiteVfsFileWriter::applyCurrentWalTransactionRecovery()` for current-source recovery from a database file already present under the VFS root and its local `-wal` sidecar.
- The method reuses the existing WAL transaction recovery boundary and atomic VFS writer, records current-source metadata, skips cleanly when no WAL sidecar exists, and rejects missing database paths.
- Added `wordpress-pager-wal-recovery-current.php` as the WordPress smoke path for a copied `wp-content/database/.ht.sqlite` file with committed WAL pages plus an uncommitted crash tail.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php && php -l lanes/libsqlite/tests/SQLitePagerWalRecoveryCurrentTest.php && php -l lanes/libsqlite/examples/wordpress-pager-wal-recovery-current.php`
  - Passed: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerWalRecoveryCurrentTest.php`
  - Passed: `1 test files, 55 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-pager-wal-recovery-current.php --self-test`
  - Passed: `wordpress-pager-wal-recovery-current self-test passed`.
- `git diff --check -- lanes/libsqlite`
  - Passed with no output.

## Non-Overlap

This patch does not add numbered production classes and does not repeat accepted checkpoint transaction planning/application, rollback-journal apply, WAL byte truncation, WAL checksum/salt recovery, readmark recovery, hot-journal checkpoint, or master-journal cache families. It extends the current VFS apply path by reading the current local database/WAL files before applying the already accepted committed-prefix recovery primitive.

## Dependency Closure

No new support component is needed. The slice reuses lane-local WAL parsing, transaction-boundary recovery, and atomic VFS file-handle application.

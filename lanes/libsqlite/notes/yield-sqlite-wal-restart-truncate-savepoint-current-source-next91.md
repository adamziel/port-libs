# WAL restart truncate savepoint current-source next91

## Behavior

Adds `SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext()`
for the savepoint path where an inner Application import savepoint is released into
its parent, then the parent is rolled back before a RESTART/TRUNCATE checkpoint.
The method validates the caller-provided current WAL bytes against the parsed WAL
source, reports current/retained/next WAL source metadata, and preserves reader
visibility across ready, busy, restart, and truncate outcomes.

This is distinct from accepted WAL byte-truncation, savepoint rollback apply,
reader-pin restart/truncate, and current-source reader visibility slices because
it covers the release-then-parent-rollback ordering with current-source
admission.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalRestartTruncateSavepointCurrentSourceNext91Test.php`
- `php -l lanes/libsqlite/examples/application-wal-restart-truncate-savepoint-current-source-next91.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalRestartTruncateSavepointCurrentSourceNext91Test.php`
  - `1 test files, 72 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-restart-truncate-savepoint-current-source-next91.php`
  - reports `restart_wal`, `truncate_wal`, current-source frame validation,
    retained source frame count, and rolled-back released frames for copied
    `wp_options` import savepoints.

## Dependency Closure

No new support component is required. The slice reuses existing native
`SQLiteWal`, `SQLiteSavepointStack`, WAL checksum/header parsing, durable
checkpoint planning, and reader visibility helpers.

## Non-Overlap

Avoids accepted restart/truncate current-source next86, checkpoint savepoint
current-source next87, reader truncate current-source next88, WAL byte
truncation, VFS savepoint rollback apply, and reader-pin restart/truncate
handoffs by covering the unhandled release-then-parent-rollback savepoint
ordering before restart/truncate checkpoint source selection.

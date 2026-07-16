# WAL Transaction Recovery VFS Apply Current Source Next157

## Behavior

- Added `SQLiteVfsFileWriter::applyWalTransactionRecoveryBoundary()` to atomically apply an existing WAL committed-prefix recovery boundary through native PHP file handles.
- The writer checkpoints only committed WAL frames into the database image, truncates the WAL sidecar to the committed prefix, syncs the touched handles, and persists the sidecar directory.
- The no-commit path leaves the database image unchanged and truncates the WAL to its validated header only.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalTransactionRecoveryVfsApplyCurrentSourceNext157Test.php`
- Result: `1 test files, 51 assertions, 0 failures`

## Non-Overlap

This avoids accepted WAL byte truncation, savepoint rollback application, rollback-journal commit/apply, checkpoint transaction planning, VFS file writer, locked writer, sync apply, and prior checksum-boundary recovery slices. The new behavior specifically applies `SQLiteWal::transactionRecoveryBoundary()` committed-prefix state to VFS files.

## Dependency Closure

No new support component is needed. The patch reuses native WAL transaction recovery boundaries, WAL parsing/checksum validation, atomic VFS file-handle writes, truncation, and sync operations already present in the lane.

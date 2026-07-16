# WAL Checksum Recovery VFS Apply Next11

## Behavior

This slice adds bounded native PHP application for raw WAL bytes that may have a corrupt checksum, salt mismatch, truncated frame tail, or bad header checksum. `SQLiteVfsFileWriter::applyWalChecksumRecoveryBoundary()` uses `SQLiteWal::checksumRecoveryBoundary()` to keep only the valid WAL prefix, checkpoint the last valid committed frames into the database image when possible, preserve valid uncommitted frames in the WAL sidecar, discard corrupt tail bytes, and sync database/WAL/directory state through existing VFS file-handle operations.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalChecksumRecoveryApplyTest.php
Focused test run: 1 selected test files (root lock skipped)
50 PASS lines
1 test files, 50 assertions, 0 failures
```

Additional verification:

```text
php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
php -l lanes/libsqlite/tests/SQLiteWalChecksumRecoveryApplyTest.php
php -l lanes/libsqlite/examples/application-wal-checksum-recovery-apply.php
php lanes/libsqlite/examples/application-wal-checksum-recovery-apply.php --self-test
git diff --check -- lanes/libsqlite
```

## Non-Overlap

Avoided accepted WAL byte truncation, parsed-WAL recovery apply, rollback-journal commit/apply, VFS savepoint rollback, WAL checkpoint transactions, and VFS sync/file-writer/process-lock clusters. This slice starts from raw corrupt WAL bytes that cannot safely be parsed as a normal `SQLiteWal` and applies the checksum recovery boundary through VFS handles.

## Dependency Closure

No new support component is needed. The implementation reuses existing native PHP WAL checksum scanning, checkpoint image creation, and VFS file-handle write/truncate/sync primitives.

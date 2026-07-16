# WAL Savepoint Restart Checkpoint Apply Current/Next 74

Slice: `wal-savepoint-rollback-checkpoint-durability-current-next74`

Behavior covered:

- Adds `SQLiteVfsFileWriter::applySavepointRestartCheckpointAppend()` for the durable VFS path where a failed Application `wp_options` import rolls back to a savepoint, checkpoints the retained WAL prefix, then persists retry WAL frames.
- Applies the final database image plus final WAL sidecar atomically through native PHP file handles, including database write/truncate/sync, WAL write/truncate/sync, and sidecar directory persistence.
- Covers restart and truncate checkpoint modes, optional WAL/directory sync suppression, busy-reader skip behavior, read-only guard behavior, and parsed WAL validity after retry-frame persistence.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointRestartCheckpointApplyCurrentNext74Test.php
Focused test run: 1 selected test files (root lock skipped)
51 PASS lines, 1 test files, 51 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-savepoint-restart-checkpoint-apply-current-next74.php
status=applied, appliedOperations=7, bytesWritten=2664, bytesTruncated=2664,
durableSyncs=2, directorySyncs=1, retainedWalFrames=2, discardedWalFrames=2,
checkpointWalAction=restart_wal, nextWalFrames=3, nextWalLastCommitFrame=2,
walContainsRetryCommit=true, walContainsRolledBackDraft=false
```

Dashboard delta:

- `phpPass`: `28200 -> 28251` for 51 newly verified lane-scoped PASS cases.
- `phpFail`: remains `0`.
- Mapped denominator unchanged at `464 / 1589`; this is focused native behavior coverage over an already mapped WAL/savepoint/checkpoint family.

Non-overlap:

- Avoids accepted WAL byte truncation, `SQLiteVfsFileWriter::applySavepointRollback()`, WAL checkpoint transaction planning, reader-pin restart/truncate checkpoint handoff, rollback-journal commit/apply, VFS sync/apply, and the batch72/73 WAL reader/checkpoint snapshot clusters.

Dependency closure:

- No new support component is required. The slice reuses existing native PHP `SQLiteSavepointStack`, `SQLiteWal`, `SQLiteWalAppendPlan`, and `SQLiteVfsFileWriter` primitives.

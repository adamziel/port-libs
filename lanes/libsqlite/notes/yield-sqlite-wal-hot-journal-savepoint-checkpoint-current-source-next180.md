# WAL Hot-Journal Savepoint Checkpoint Current Source Next180

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a narrow
follow-up to next177. The new plan applies next177's ordered resume operations
to a caller-visible file map atomically:

- verifies write payload length and SHA-256 before publishing database, WAL, or
  hot-journal bytes;
- stages write/truncate/delete/sync/directory-sync operations in order;
- preserves the original file map on simulated mid-batch failure;
- verifies the published file map against next177 payload metadata for
  idempotent resume.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext180Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 55 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next180.php
status: wal-hot-journal-savepoint-checkpoint-current-source-next180
operationNames: write, truncate, sync, sync_directory
stagedPayloadPaths: /srv/www/wp-content/database/wp-next180.sqlite
```

## Non-Overlap

This slice materializes next177 operation metadata into an atomic file-map
publication and idempotence verifier. It does not repeat next174 file-state
admission, next177 operation planning, VFS writer/sync/lock implementation,
rollback-journal commit/apply, WAL byte truncation, checkpoint transaction
planning, or reader restart/checkpoint snapshot surfaces.

## Dependency Closure

No new support component is needed. The implementation reuses lane-local WAL,
rollback-journal, savepoint/checkpoint resume, and VFS operation metadata
primitives.

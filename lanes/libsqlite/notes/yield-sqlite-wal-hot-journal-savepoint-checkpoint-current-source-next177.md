# WAL Hot-Journal Savepoint Checkpoint Current Source Next177

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a narrow
follow-up to next174. The new plan turns a verified next174 file-state resume
into guarded atomic VFS resume operations:

- rewrites and syncs missing or stale database/WAL checkpoint payloads before
  hot-journal retirement;
- restores a stale hot journal while the current checkpoint payloads are not
  yet durable;
- deletes the hot journal only after next174 admits current-source deletion;
- requires an exclusive lock before apply and directory sync before publishing
  non-empty operation plans.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext177Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 50 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next177.php
status: wal-hot-journal-savepoint-checkpoint-current-source-next177
operationNames: write, truncate, sync, sync_directory
payloadPaths: /srv/www/wp-content/database/wp-next177.sqlite
```

## Non-Overlap

This slice extends next174 verified file-state replay with ordered atomic resume
operations. It does not repeat accepted WAL byte truncation, WAL checkpoint
transaction planning, VFS writer/sync/lock application, rollback-journal
commit/apply, hot-journal recovery admission, or reader restart/checkpoint
snapshot surfaces.

## Dependency Closure

No new support component is needed. The implementation reuses existing native
PHP WAL, rollback-journal, savepoint, current-source verification, and VFS
operation metadata primitives.

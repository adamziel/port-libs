# WAL Hot-Journal Savepoint Checkpoint Current Source Next215

## Behavior

- Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`.
- Models `RESTART` / `TRUNCATE` checkpoint completion after next212 `PASSIVE`
  checkpoint reader-pin discovery.
- Requires stale readers to reopen on the current database/WAL/writer digests,
  current reader pins to drain, and the checkpoint to cover the requested WAL
  frame before WAL reset/truncate is admitted.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext215Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 70 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-wal-restart-checkpoint-reader-reopen-current-source-next215.php
{
    "status": "wal-hot-journal-savepoint-checkpoint-current-source-next215",
    "wal_action": "reset_wal_header_after_restart_checkpoint",
    "database_action": "write_frames_through_215",
    "new_current_source_epoch": 216,
    "admitted_reopen_reader_names": [
        "stale-plugin-reader"
    ],
    "dependency_closure": "no new support component needed; reuses next212 passive checkpoint reader-pin metadata and current-source digest fences"
}
```

## Non-Overlap

This slice follows next212 passive progress. It does not repeat next212 passive
reader-pin classification, next209 writer fences, WAL byte truncation, VFS
savepoint rollback, rollback-journal commit/apply, sync plans, or checkpoint
transaction planning.

## Dependency Closure

No new support component is needed. The plan reuses next212 passive checkpoint
reader-pin metadata and current-source digest fences.

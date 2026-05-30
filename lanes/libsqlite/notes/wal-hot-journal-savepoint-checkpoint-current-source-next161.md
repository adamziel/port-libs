# WAL Hot-Journal Savepoint Checkpoint Current Source Next161

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, covering reader-cache source-token fencing after:

- hot rollback-journal recovery restores dirty database pages;
- `ROLLBACK TO` restores savepoint before-images on top of the recovered current source;
- the current WAL generation is checkpointed with an active reader;
- the next WAL generation is admitted only after cache entries are retained or invalidated against the checkpoint source token.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext161Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 84 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next161.php
```

Non-overlap: this does not repeat next159 checkpoint payload writes, WAL byte truncation, VFS savepoint rollback application, rollback-journal apply/commit, or checkpoint transaction planning. It focuses on reader-cache admission and invalidation across the current-source token boundary.

Dependency closure: no new support component is needed; it reuses WAL parsing, reader snapshots, durable checkpoint planning, and savepoint before-image materialization already present in the lane.

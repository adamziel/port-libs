# WAL Release/Rollback Checkpoint Current/Next 55

Slice: `yield-sqlite-wal-snapshot-savepoint-checkpoint-current-next55`

Behavior covered:

- Adds `SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentNext()` for the WAL savepoint path where a nested Application import savepoint is `RELEASE`d into its parent, then `ROLLBACK TO` the parent truncates the merged WAL frame set before checkpoint/restart/truncate visibility is evaluated.
- Proves that frames from the released nested savepoint are still discarded by the parent rollback boundary, including committed nested WAL frames for copied `wp_options` plugin settings/autoload-index pages.
- Covers restart, truncate, and busy-reader current/next visibility so current readers keep retained WAL snapshots while next readers see checkpointed database state or a preserved WAL when a reader blocks reset.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReleaseRollbackCheckpointCurrentNext55Test.php
Focused test run: 1 selected test files (root lock skipped)
65 PASS lines, 1 test files, 65 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-release-rollback-checkpoint-current-next.php
```

Dashboard delta:

- `phpPass`: `20008 -> 20073` for 65 newly verified lane-scoped PASS cases.
- `phpFail`: remains `0`.
- Mapped denominator unchanged; this is focused PHP behavior coverage over an already mapped WAL/savepoint/checkpoint family, not a new upstream inventory row.

Non-overlap:

- Avoids accepted WAL byte truncation, WAL savepoint reader restart, VFS savepoint rollback apply, rollback-journal commit/apply, WAL checkpoint transactions, SELECT/JOIN/GROUP text, JSON table cursor/source/constraints, Unicode GLOB, B-tree page move/root-collapse/overflow release, and VFS lock/writer/sync clusters.

Dependency closure:

- No new support component is required. The slice reuses existing native PHP `SQLiteSavepointStack`, `SQLiteWal`, and `SQLiteWalSavepointCheckpointPlan` primitives.

# WAL Checkpoint Reader Savepoint Yield Current/Next 48

## Behavior

Adds a bounded WAL savepoint/checkpoint yield view for copied Application import
diagnostics. `SQLiteWalSavepointCheckpointPlan::yieldReaderSavepointCurrentNext()`
returns three reader-visible stages:

- original current reader before `ROLLBACK TO`;
- current reader after savepoint WAL prefix truncation;
- next reader after checkpoint durability planning.

The slice is intentionally separate from accepted WAL byte truncation,
checkpoint transaction admission, VFS file-writer application, and savepoint
rollback application. It composes existing WAL primitives to expose the reader
yield boundary and preserves busy restart/truncate behavior when a current
reader still pins the retained WAL prefix.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointReaderSavepointYieldCurrentNext48Test.php
Focused test run: 1 selected test files (root lock skipped)
61 PASS lines
1 test files, 61 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-wal-checkpoint-savepoint-yield.php --self-test
application-wal-checkpoint-savepoint-yield self-test passed
```

## Status Delta

- Focused `phpPass` delta: `+61` PASS lines.
- `lane-status.json` `phpPass`: `17373 -> 17434` in this isolated worktree.
- Root harness: not run, isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses existing native PHP WAL,
savepoint, and checkpoint primitives and adds only a reader-yield composition
surface.

## Non-Overlap

Avoids accepted `SQLiteSavepointStack::walRollbackToByteTruncationPlan()`,
`SQLitePagerCheckpointTransactionPlan`, `SQLiteVfsFileWriter` savepoint/VFS
application, rollback-journal apply/commit, VFS locked writer/sync, and accepted
WAL checkpoint transaction/current-reader visibility clusters.

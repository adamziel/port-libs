# WAL Savepoint Reader Checkpoint Current Source Next99

## Behavior

Adds `SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointRecoveryCurrentSourceNext()` for a current-source WAL savepoint rollback/checkpoint timeline.

The slice is narrower than accepted batch90/batch94 coverage. Batch90 verifies exact current WAL bytes for pinned-reader savepoint rollback, and batch94 verifies reader release can unblock restart/truncate checkpoint. This next99 slice combines those into one source timeline with current WAL SHA-256, retained WAL SHA-256, pinned next-source summary, released next-source summary, frame source offsets, source transitions, and current/next reader evidence for WordPress import diagnostics.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointReaderCheckpointCurrentSourceNext99Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 74 assertions, 0 failures
```

Smoke:

```text
php lanes/libsqlite/examples/wordpress-wal-savepoint-reader-checkpoint-current-source-next99.php --self-test
wordpress-wal-savepoint-reader-checkpoint-current-source-next99 self-test passed
```

## Dashboard Delta

Expected focused PASS-line movement is `+74` for the new lane-scoped test file, with `0` failures. Mapped coverage is unchanged because this is current-source WAL/savepoint behavior under existing mapped WAL inventory.

## Dependency Closure

No new support component is needed. This reuses the native PHP WAL parser, savepoint stack, durable checkpoint result, and source verification helpers.

## Non-Overlap

Avoids accepted WAL byte truncation, VFS savepoint rollback application, rollback-journal apply, checkpoint transaction planning, reader-pin restart/truncate handoff, batch90 current-source pinned-reader coverage, and batch94 reader-release checkpoint coverage. The new behavior is the combined current-source reader timeline across pinned and released checkpoint outcomes.

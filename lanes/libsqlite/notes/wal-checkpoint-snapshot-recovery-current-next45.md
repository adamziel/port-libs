# WAL checkpoint snapshot recovery current/next45

## Behavior

Added `SQLiteWalCheckpointSnapshotRecoveryPlan::currentNextAfterCheckpointRecovery()` for the WAL recovery boundary after a checkpoint has materialized database pages. The helper keeps the pre-crash reader snapshot pinned to the original WAL frame range, then models the next opener against durable checkpoint database bytes plus the recovered WAL sidecar.

Covered outcomes:

- restart checkpoint with a valid header-only WAL sidecar;
- truncate checkpoint with an empty WAL sidecar;
- truncated WAL sidecar fallback to checkpoint database bytes;
- checksum-corrupt WAL sidecar fallback to checkpoint database bytes;
- latest reader snapshots matching next recovered checkpoint state.

## Evidence

Focused command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointSnapshotRecoveryCurrentNext45Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 56 assertions, 0 failures
```

Example smoke:

```bash
php lanes/libsqlite/examples/application-wal-checkpoint-snapshot-recovery-current-next.php
```

## Non-overlap

This does not repeat accepted WAL checkpoint transactions, VFS file writer/apply, savepoint byte truncation, rollback-journal commit/apply, current-reader visibility diagnostics, or WAL append transaction persistence. The slice is recovery classification at the current-reader/next-opener boundary after checkpoint output is recovered or rejected.

## Dependency closure

No new support component is needed. The slice reuses existing `SQLiteWal`, durable checkpoint bytes, and native PHP WAL parsing/checksum validation.

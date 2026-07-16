# WAL recovery checkpoint savepoint current-source next82

## Behavior

`SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo()` now validates that the supplied WAL bytes are the same current source as the parsed `SQLiteWal` before savepoint WAL byte truncation and checkpoint crash-recovery planning. This prevents a Application import retry from checkpointing or resetting stale WAL bytes from a previous salt, checkpoint sequence, or shorter frame prefix after rolling back a failed plugin savepoint.

## Focused evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalRecoveryCheckpointSavepointCurrentSourceNext82Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 56 assertions, 0 failures
```

New focused PASS lines: `56`.

## Non-overlap

This does not repeat accepted WAL byte truncation, savepoint page-image rollback, WAL checkpoint transactions, rollback-journal apply, reader-pin restart/truncate handoff, or batch79 `afterRollbackTo()` current-source checks. The new guard is specifically on the crash-recovery checkpoint entry point after savepoint rollback.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP WAL parsing/checksum validation, `SQLiteSavepointStack`, checkpoint crash-recovery planning, and VFS write-plan evidence.

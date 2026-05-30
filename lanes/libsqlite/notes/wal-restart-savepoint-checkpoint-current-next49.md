# WAL restart savepoint checkpoint current next49

Status: focused PHP corpus growth for WAL savepoint rollback followed by RESTART/TRUNCATE checkpoint and the next writer transaction.

This slice adds `SQLiteWalAppendPlan::savepointRestartCheckpointCurrentNext()` for copied Application database imports. It composes the existing savepoint WAL byte-truncation primitive with durable RESTART/TRUNCATE checkpoint planning and WAL append transaction planning, then reports the current reader view of the retained WAL prefix versus the next reader view after the checkpoint database and appended WAL frames.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteWalAppendPlan.php
php -l lanes/libsqlite/tests/SQLiteWalRestartSavepointCheckpointCurrentNext49Test.php
php -l lanes/libsqlite/examples/application-wal-restart-savepoint-checkpoint-current-next49.php
No syntax errors detected in lanes/libsqlite/src/SQLiteWalAppendPlan.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteWalRestartSavepointCheckpointCurrentNext49Test.php
No syntax errors detected in lanes/libsqlite/examples/application-wal-restart-savepoint-checkpoint-current-next49.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalRestartSavepointCheckpointCurrentNext49Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 71 assertions, 0 failures

php lanes/libsqlite/examples/application-wal-restart-savepoint-checkpoint-current-next49.php
{
    "status": "planned",
    "savepoint": "plugin_batch",
    "retained_frames": 2,
    "discarded_frames": 2,
    "checkpoint_action": "restart_wal",
    "current_sources": ["wal", "wal", "missing"],
    "next_sources": ["database", "wal", "wal"],
    "next_commit_frame": 2
}
```

Expected dashboard movement: `phpPass` +71, from 17920 to 17991, from the 71 independent PASS lines in `SQLiteWalRestartSavepointCheckpointCurrentNext49Test.php`. `benchmarkDenominator.mapped` is unchanged because this slice does not claim a fresh upstream inventory unit.

Non-overlap: this avoids accepted WAL append transaction persistence, WAL checkpoint transactions, WAL savepoint byte truncation as a standalone surface, VFS savepoint rollback application, rollback-journal commit/apply, VFS writer/sync/lock clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, B-tree page move/root-collapse/overflow release work, Unicode GLOB, and batch23 metadata/planner/VDBE work. The new behavior is the composed current/next boundary after a savepoint rollback truncates the WAL, a restart/truncate checkpoint resets the retained prefix, and the next writer appends committed plus uncommitted transaction frames.

Dependency closure: no new support component is needed. The slice reuses existing lane-local WAL checksum/frame parsing, savepoint WAL rollback metadata, durable checkpoint result planning, and WAL append transaction primitives.

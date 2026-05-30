# WAL Crash Recovery Checkpoint Current Next33

Status: focused PHP behavior growth for WAL checkpoint crash-recovery current/next reader boundaries.

This slice adds `SQLiteWalCheckpointCrashRecoveryPlan`, a bounded native PHP planner for restart/truncate checkpoints that crash after checkpointed database pages are durable but before WAL sidecar reset/truncate state or directory sync is fully durable. It reports:

- current reader visibility from the original database image plus pre-crash WAL frames;
- next opener visibility when recovery replays the still-present WAL idempotently after `after_database_sync`;
- next opener visibility when the restarted header or truncated WAL sidecar is already persisted before directory sync;
- applied versus pending checkpoint write/sync operations for each crash phase;
- busy reader reset blocking without applying partial checkpoint state.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalCheckpointCrashRecoveryPlan.php
# No syntax errors detected in lanes/libsqlite/src/SQLiteWalCheckpointCrashRecoveryPlan.php

php -l lanes/libsqlite/tests/SQLiteWalCrashRecoveryCheckpointCurrentNext33Test.php
# No syntax errors detected in lanes/libsqlite/tests/SQLiteWalCrashRecoveryCheckpointCurrentNext33Test.php

php -l lanes/libsqlite/examples/application-wal-crash-recovery-checkpoint-current-next33.php
# No syntax errors detected in lanes/libsqlite/examples/application-wal-crash-recovery-checkpoint-current-next33.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCrashRecoveryCheckpointCurrentNext33Test.php
# 1 test files, 69 assertions, 0 failures

php lanes/libsqlite/examples/application-wal-crash-recovery-checkpoint-current-next33.php
# Prints recovered checkpoint visibility for copied wp_options pages after an after_database_sync crash.
```

Expected dashboard movement: `phpPass` +69, from 11206 to 11275, from the 69 independent PASS lines in `SQLiteWalCrashRecoveryCheckpointCurrentNext33Test.php`. `benchmarkDenominator.mapped` is unchanged because this is lane-scoped PHP behavior coverage rather than a newly mapped upstream inventory unit.

Non-overlap: this avoids accepted WAL hot-journal checkpoint recovery, WAL checkpoint transactions, WAL checkpoint append current/next, WAL append transaction persistence, WAL byte truncation, WAL checksum/corrupt-boundary recovery, WAL SHM restart/read-mark diagnostics, VFS savepoint rollback, rollback-journal apply/commit, VFS file-writer/sync/lock clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, B-tree page move/root-collapse/overflow release work, and Unicode GLOB. The new surface is crash-window recovery after checkpoint database pages are durable but before the WAL reset/truncate sidecar sequence is fully durable.

Dependency closure: no new support component is needed. The slice reuses existing lane-local WAL checkpoint result, reader snapshot, and VFS file-write plan primitives.

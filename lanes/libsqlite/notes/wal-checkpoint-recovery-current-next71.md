# WAL Checkpoint Recovery Current/Next 71

## Behavior

Adds `SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes()` for the raw-WAL recovery path where a checkpoint crash leaves durable database pages beside a WAL file that may contain a valid uncommitted tail followed by a corrupt frame. The planner first finds the checksum-valid and transaction-committed prefix, checkpoints only the committed frames, and then reports current-reader visibility separately from the next opener after restart/truncate sidecar recovery.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointRecoveryCurrentNext71Test.php`
- `php lanes/libsqlite/examples/application-wal-checkpoint-recovery-current-next71.php --self-test`
- `php -l lanes/libsqlite/src/SQLiteWalCheckpointCrashRecoveryPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalCheckpointRecoveryCurrentNext71Test.php`
- `php -l lanes/libsqlite/examples/application-wal-checkpoint-recovery-current-next71.php`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This does not repeat accepted WAL checkpoint transactions, WAL byte truncation, VFS writer application, reader-pin restart/truncate handoff, or checksum boundary VFS apply. The new behavior is the raw checkpoint-recovery current/next planner that combines checksum/transaction recovery with current-reader and next-opener visibility.

## Dependency Closure

No new support component is needed. The slice reuses the existing WAL parser, checksum/transaction recovery boundary, reader snapshot visibility, and bounded checkpoint/write-operation diagnostics.

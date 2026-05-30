# WAL Savepoint Crash-Recovery Checkpoint Current/Next 75

This slice adds `SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo()` for the WAL path where `ROLLBACK TO` first discards savepoint frames and a restart/truncate checkpoint then crashes before or after WAL sidecar persistence. The planner checkpoints only the retained WAL prefix, reports current-reader versus next-opener visibility, and proves discarded savepoint frames are not recovered even when the discarded tail contains corrupt bytes.

Application smoke:

- `lanes/libsqlite/examples/application-wal-savepoint-crash-recovery-checkpoint-current-next75.php` models a copied `wp_options` plugin import where failed savepoint frames for `active_plugins` and a transient row are rolled back before checkpoint crash recovery.

Focused evidence:

```text
php -l lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php

php -l lanes/libsqlite/tests/SQLiteWalSavepointCrashRecoveryCheckpointCurrentNext75Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteWalSavepointCrashRecoveryCheckpointCurrentNext75Test.php

php -l lanes/libsqlite/examples/application-wal-savepoint-crash-recovery-checkpoint-current-next75.php
No syntax errors detected in lanes/libsqlite/examples/application-wal-savepoint-crash-recovery-checkpoint-current-next75.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointCrashRecoveryCheckpointCurrentNext75Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 68 assertions, 0 failures
```

Dependency closure: no new support component is needed. This reuses the existing `SQLiteSavepointStack` WAL truncation model and `SQLiteWalCheckpointCrashRecoveryPlan` recovery boundary.

Non-overlap: avoids accepted WAL byte truncation, VFS savepoint rollback apply, checkpoint transaction, rollback-journal apply, and WAL checksum/read-mark recovery by composing retained-savepoint WAL prefix recovery with checkpoint crash current/next visibility.

# WAL hot-journal savepoint checkpoint current-source next168

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a publication gate for the Application import path where hot rollback-journal recovery and savepoint rollback have produced a checkpoint current-source token, but the pager still needs to decide whether it may publish that source, delete the hot journal, preserve/reset WAL sidecars, sync the directory, and advertise the next WAL generation.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext168Test.php
php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next168.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext168Test.php
1 test files, 69 assertions, 0 failures
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next168.php
```

The example reports `wal-hot-journal-savepoint-checkpoint-current-source-next168`, allows hot-journal deletion after source publish, preserves WAL for readers, and publishes the next WAL generation.

Non-overlap: this does not repeat next161 cache-token rebasing, next164 reader admission, VFS byte writes, WAL byte truncation, checkpoint transaction planning, rollback-journal apply, or savepoint rollback application. It gates the final source publication and sidecar lifecycle decisions after those earlier stages.

Dependency closure: no new support component is needed; it composes native WAL parsing, hot-journal recovery, savepoint rollback, checkpoint source-token fencing, and reader admission gates.

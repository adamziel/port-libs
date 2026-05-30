# WAL checkpoint append current next25

Status: focused PHP corpus growth for WAL checkpoint-then-append current/next visibility.

This slice adds `SQLiteWalAppendPlan::checkpointAppendCurrentNext()` for copied Application database imports. It composes a completed RESTART/TRUNCATE checkpoint with the next WAL writer transaction, generates a fresh WAL header when TRUNCATE leaves an empty sidecar, appends committed and uncommitted transaction frames with chained checksums, and reports current-reader versus next-reader page visibility.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteWalAppendPlan.php
php -l lanes/libsqlite/tests/SQLiteWalCheckpointAppendCurrentNext25Test.php
php -l lanes/libsqlite/examples/application-wal-checkpoint-append-current-next25.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointAppendCurrentNext25Test.php
php lanes/libsqlite/examples/application-wal-checkpoint-append-current-next25.php
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
1 test files, 64 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +64, from 8739 to 8803, from the 64 independent PASS lines in `SQLiteWalCheckpointAppendCurrentNext25Test.php`.

Non-overlap: this avoids accepted WAL append transaction persistence, WAL checkpoint transactions, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, VFS writer/sync/lock clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, B-tree page move/root-collapse/overflow release work, Unicode GLOB, and batch23 metadata/planner/VDBE work. The new behavior is the current/next reader boundary when a fresh post-checkpoint WAL receives the next appended transaction.

Dependency closure: no new support component is needed. The slice reuses existing lane-local WAL checkpoint result, WAL frame checksum, reader snapshot, and append planning primitives.

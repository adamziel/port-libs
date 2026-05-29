# WAL Reader Restart Checkpoint Current Next43

Status: focused PHP behavior growth for current and next WAL readers around a RESTART/TRUNCATE checkpoint.

This slice adds `SQLiteWalReaderRestartCheckpointPlan`, a bounded native PHP planner that composes existing WAL, SHM read-mark, and restart checkpoint primitives into copied WordPress database diagnostics. It reports the current reader snapshot, next reader snapshot, pinned read-mark transition, checkpoint action, page source/frame indexes, changed pages, and the WAL/database operations needed for the next reader.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteWalReaderRestartCheckpointPlan.php
php -l lanes/libsqlite/tests/SQLiteWalReaderRestartCheckpointCurrentNext43Test.php
php -l lanes/libsqlite/examples/wordpress-wal-reader-restart-checkpoint-current-next43.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderRestartCheckpointCurrentNext43Test.php
php lanes/libsqlite/examples/wordpress-wal-reader-restart-checkpoint-current-next43.php --self-test
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 70 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +70, from 15880 to 15950, from the 70 independent PASS lines in `SQLiteWalReaderRestartCheckpointCurrentNext43Test.php`.

Non-overlap: this avoids accepted WAL checkpoint append, WAL checkpoint transaction, WAL savepoint byte truncation, VFS savepoint rollback, rollback-journal apply/commit, WAL checksum recovery, hot-journal/WAL recovery, JSON table cursor/source/constraint work, SELECT SQL text/subquery/group/order clusters, B-tree page move/root-collapse/overflow release work, Unicode GLOB, and batch35 WAL savepoint reader-pin checkpoints. The new behavior is the restart/truncate checkpoint current-reader versus next-reader boundary using SHM read marks, without appending new WAL frames or applying a savepoint writer wrapper.

Dependency closure: no new support component is needed. The slice reuses lane-local WAL reader snapshots, durable checkpoint planning, SHM read-mark parsing, and current/next reader visibility primitives.

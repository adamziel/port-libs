# WAL checkpoint savepoint release current next51

Status: focused PHP behavior growth for WAL checkpoint reader visibility after `RELEASE SAVEPOINT` merges current savepoint frames into the outer transaction.

This slice adds `SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentNext()`, a bounded native PHP current/next planner for copied Application import transactions. It models a reader before savepoint release, the current reader after release has merged nested WAL frames, and the next reader after `PASSIVE`, `RESTART`, or `TRUNCATE` checkpoint planning.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointSavepointReleaseCurrentNext51Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 67 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +67 from the 67 independent PASS lines in `SQLiteWalCheckpointSavepointReleaseCurrentNext51Test.php`.

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-checkpoint-savepoint-release-current-next51.php
application-wal-checkpoint-savepoint-release-current-next51 self-test passed
```

Non-overlap: avoids accepted WAL rollback-to byte truncation, savepoint page-image rollback, VFS savepoint rollback apply, batch48 WAL checkpoint reader/savepoint yield, WAL checkpoint transactions, rollback-journal commit/apply, VFS writer/sync/lock clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, B-tree page move/root-collapse/overflow release work, Unicode GLOB, and batch23 WAL append transaction persistence. The new behavior is `RELEASE SAVEPOINT` current/next reader visibility, where released frames are retained and checkpointed rather than rolled back or truncated to the savepoint prefix.

Dependency closure: no new support component is needed. The slice reuses lane-local WAL parsing/checksums, savepoint release metadata, durable checkpoint result planning, and reader snapshot page-image primitives.

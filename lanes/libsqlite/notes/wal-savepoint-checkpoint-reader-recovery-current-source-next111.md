# WAL savepoint checkpoint reader recovery current source next111

Status: focused PHP behavior growth for WAL savepoint rollback and checkpoint reader recovery on the current source.

This slice adds `SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan`. It models the current-source order used by WordPress import retries when a reader end-frame points beyond the durable committed WAL prefix: first recover/clamp the reader to the committed prefix, then apply `ROLLBACK TO` savepoint WAL truncation, then compare pinned-reader checkpoint behavior against the released-reader restart/truncate result.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNext111Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 74 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/wordpress-wal-savepoint-checkpoint-reader-recovery-current-source-next111.php --self-test
wordpress-wal-savepoint-checkpoint-reader-recovery-current-source-next111 self-test passed
```

Non-overlap: avoids accepted WAL checksum/salt recovery, WAL restart/truncate reader behavior, WAL checkpoint reader savepoint next104, WAL recovery/checkpoint/savepoint next100, WAL byte truncation, VFS savepoint rollback apply, rollback-journal/super-journal paths, and accepted JSON/B-tree/SQL/VFS clusters. The new surface is stale reader recovery against the committed current WAL prefix before savepoint rollback and checkpoint release decisions.

Dependency closure: no new support component is needed. The slice reuses lane-local WAL transaction recovery, savepoint WAL truncation, and durable checkpoint primitives.

Next task: continue with broader WAL pager/VFS transaction application or another non-overlapping reader/checkpoint durability edge that applies bytes to native handles.

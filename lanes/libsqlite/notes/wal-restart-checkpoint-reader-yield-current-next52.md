# WAL restart checkpoint reader yield current next52

Status: focused PHP corpus growth for WAL restart checkpoint reader-yield current/next behavior.

This slice adds `SQLiteWal::restartCheckpointReaderYieldCurrentNext()` for copied Application database imports. It models a RESTART/TRUNCATE checkpoint first blocked by a current SHM reader read mark, then recomputes the checkpoint after selected reader slots yield. The plan reports current-reader pinned visibility, yielded read-mark state, reset/truncate checkpoint output, and next-reader database/restarted-WAL visibility.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteWalRestartCheckpointReaderYieldCurrentNext52Test.php
php -l lanes/libsqlite/examples/application-wal-restart-checkpoint-reader-yield-current-next52.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalRestartCheckpointReaderYieldCurrentNext52Test.php
php lanes/libsqlite/examples/application-wal-restart-checkpoint-reader-yield-current-next52.php
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
1 test files, 52 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +52, from 19277 to 19329, from the 52 independent PASS lines in `SQLiteWalRestartCheckpointReaderYieldCurrentNext52Test.php`.

Non-overlap: this avoids accepted WAL checkpoint transaction application, WAL savepoint byte truncation, WAL append transaction persistence, WAL restart/savepoint checkpoints, WAL checkpoint reader/savepoint yields, WAL SHM restart diagnostics, VFS writer/sync/lock/rollback clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, B-tree page move/root-collapse/overflow release work, and Unicode GLOB. The new behavior is the current-reader to next-reader checkpoint boundary after the blocking SHM read mark yields.

Dependency closure: no new support component is needed. The slice reuses existing lane-local WAL checkpoint, SHM read-mark, durable WAL sidecar, and reader visibility primitives.

# WAL checkpoint restart reader map current next37

Status: focused PHP corpus growth for WAL checkpoint RESTART/TRUNCATE reader-map visibility.

This slice adds `SQLiteWal::restartReadMarkReaderMapTransition()` for copied Application database imports. It composes the existing SHM read-mark restart transition with page-image visibility maps so a current reader pinned by read marks keeps its old WAL snapshot while the next reader maps pages from either the preserved WAL, checkpointed database image, restarted WAL header, or truncated WAL state.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteWalCheckpointRestartReaderMapCurrentNext37Test.php
php -l lanes/libsqlite/examples/application-wal-checkpoint-restart-reader-map-current-next37.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointRestartReaderMapCurrentNext37Test.php
php lanes/libsqlite/examples/application-wal-checkpoint-restart-reader-map-current-next37.php
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 61 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +61, from 12903 to 12964, from the 61 independent PASS lines in `SQLiteWalCheckpointRestartReaderMapCurrentNext37Test.php`.

Non-overlap: this avoids accepted WAL checkpoint transactions, WAL byte truncation, VFS savepoint rollback/apply, VFS file writer/sync/lock clusters, rollback-journal apply/commit, WAL checkpoint append, WAL checksum recovery apply, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, B-tree page move/root-collapse/overflow release work, Unicode GLOB, and batch30 surfaces. The new behavior is the explicit current-reader versus next-reader page map across a checkpoint restart read-mark transition.

Dependency closure: no new support component is needed. The slice reuses existing lane-local WAL parsing, durable checkpoint result, SHM wal-index read-mark parsing, and reader snapshot page-image primitives.

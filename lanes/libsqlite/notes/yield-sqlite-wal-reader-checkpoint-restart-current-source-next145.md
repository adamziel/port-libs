# WAL reader checkpoint restart current-source next145

Status: focused PHP behavior growth for `wal-reader-savepoint-checkpoint-restart-current-source-next145`.

This slice adds `SQLiteWalSavepointCheckpointPlan::readerCheckpointRestartSavepointCurrentSourceNext()` for the current-source WAL edge where a writer rolls back a savepoint to a retained WAL prefix, an active reader still pins rolled-back frames, and a released-reader RESTART checkpoint keeps a fresh WAL header for the retry writer. It validates the active and released SHM images against the parsed WAL source before trusting reset state.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointRestartCurrentSourceNext145Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 77 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-reader-checkpoint-restart-current-source-next145.php
application-wal-reader-checkpoint-restart-current-source-next145 self-test passed
```

Dependency closure: no new support component needed; this reuses the native PHP WAL parser/checkpoint/savepoint, SHM read-mark, and WAL append planning helpers.

Non-overlap: avoids accepted WAL byte truncation, next127 reader restart/savepoint without SHM release validation, next142 truncate reset, WAL checkpoint transactions, VFS savepoint rollback, VFS file writer/sync/lock clusters, rollback-journal commit/apply, JSON table source/cursor/constraint work, B-tree overflow/page-move/root-collapse work, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new behavior is the RESTART checkpoint branch that preserves a fresh header after reader release while the active current-source reader still sees rolled-back frames.

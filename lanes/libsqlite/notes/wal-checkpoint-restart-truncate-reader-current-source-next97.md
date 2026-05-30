# WAL checkpoint restart/truncate reader current-source next97

Status: focused PHP behavior growth for WAL checkpoint restart/truncate current-source validation with a current reader and a newer reader.

This slice adds `SQLiteWal::checkpointRestartTruncateReaderPreserveCurrentSourceNext()`. It validates raw current WAL sidecar bytes, runs the same pinned-reader state through both RESTART and TRUNCATE checkpoint modes, proves the newer reader keeps both reset modes busy after the old current reader releases, then verifies the final all-reader-released outcomes diverge correctly: RESTART leaves a fresh WAL header and TRUNCATE removes the WAL sidecar while both expose the same checkpointed database image to the next reader.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointRestartTruncateReaderCurrentSourceNext97Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 62 assertions, 0 failures
```

Additional verification:

```sh
php lanes/libsqlite/examples/application-wal-checkpoint-restart-truncate-reader-current-source-next97.php --self-test
# application-wal-checkpoint-restart-truncate-reader-current-source-next97 self-test passed
```

Expected dashboard movement: `phpPass` +62, from 36750 to 36812, from the 62 independent PASS lines in `SQLiteWalCheckpointRestartTruncateReaderCurrentSourceNext97Test.php`. Mapped upstream coverage is unchanged because this composes already mapped WAL restart, truncate, read-mark, checksum, and current-source primitives.

Non-overlap: this avoids accepted WAL reader-pin restart/truncate handoff, WAL reader checkpoint restart current-source next89, drained-reader truncate next72, WAL checkpoint/savepoint current-source slices, VFS savepoint rollback, rollback-journal commit/apply, super-journal commits, VFS writer/sync/lock clusters, B-tree page move/root collapse/overflow/freelist clusters, JSON table cursor/source/constraint work, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB. The new behavior is the paired RESTART/TRUNCATE comparison for one validated current WAL source where a newer reader blocks both reset modes until every read mark releases.

Dependency closure: no new support component is needed. The slice reuses lane-local WAL parsing/checksum validation, SHM read-mark parsing, durable checkpoint result planning, and reader snapshot helpers.

Next task: move WAL work toward pager/VFS transaction durability or actual checkpoint application through file handles; avoid another reader-source metadata variant unless it changes write application behavior.

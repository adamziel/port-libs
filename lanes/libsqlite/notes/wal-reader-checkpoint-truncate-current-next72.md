# WAL reader checkpoint truncate current-next72

Status: focused PHP behavior growth for a drained-reader TRUNCATE checkpoint boundary.

This slice adds `SQLiteWal::checkpointTruncateCurrentNext()`. It captures the current reader snapshot through a chosen WAL frame, applies a TRUNCATE checkpoint after readers have drained, removes the WAL sidecar, and proves the next reader resolves the same latest committed page images from the checkpointed database image rather than from WAL frames.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteWalReaderCheckpointTruncateCurrentNext72Test.php
php -l lanes/libsqlite/examples/wordpress-wal-reader-checkpoint-truncate-current-next72.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointTruncateCurrentNext72Test.php
php lanes/libsqlite/examples/wordpress-wal-reader-checkpoint-truncate-current-next72.php --self-test
git diff --check -- lanes/libsqlite
```

Expected focused movement: `+56` PASS lines / assertions from the new focused test file. `lane-status.json` is updated from `26631` to `26687` `phpPass`; mapped upstream coverage remains `464 / 1589` because this is a focused behavior slice, not a new upstream denominator admission row.

Non-overlap: this avoids accepted WAL reader-pin restart/truncate handoff, reader-slot append handoff, WAL byte truncation, checkpoint transaction planning, VFS writer/sync/lock/rollback-journal application, savepoint rollback application, B-tree pointer-map/overflow/root/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is the no-reader TRUNCATE checkpoint current/next boundary where the WAL sidecar is removed and the next reader must fall back to the checkpointed database image.

Dependency closure: no new support component is needed. The slice reuses lane-local WAL checksum parsing, checkpoint database image materialization, durable checkpoint sidecar output, and reader snapshot primitives.

Next task: continue with broader pager/VFS transaction durability or WAL checkpoint application through real file handles; avoid another reader-pin wrapper unless it covers a distinct storage state transition.

# WAL restart truncate reader current-source next102

Status: focused PHP behavior growth for a WAL restart/truncate reader current-source boundary.

This slice adds `SQLiteWal::checkpointRestartTruncateReaderRecoveryCurrentSourceNext()`. It verifies the raw current WAL sidecar bytes and also checks that the current SHM wal-index salt and `mxFrame` match that same WAL before trusting restart/truncate checkpoint state. The plan reports current, next-reader, and all-released SHM sources plus source-transition rows for current reader, pinned next reader, restarted WAL header, and truncated WAL sidecar outcomes.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalRestartTruncateReaderCurrentSourceNext102Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 65 assertions, 0 failures
```

Additional verification:

```text
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteWalRestartTruncateReaderCurrentSourceNext102Test.php
php -l lanes/libsqlite/examples/wordpress-wal-restart-truncate-reader-current-source-next102.php
php lanes/libsqlite/examples/wordpress-wal-restart-truncate-reader-current-source-next102.php --self-test
git diff --check -- lanes/libsqlite
```

PASS delta: `+65` focused PASS lines. `lane-status.json` `phpPass` moves from `39474` to `39539`. Mapped upstream coverage is unchanged because this composes already mapped WAL restart/truncate, read-mark, checkpoint, current-source, and SHM validation primitives.

Non-overlap: this avoids accepted WAL reader-pin restart/truncate handoff, WAL reader checkpoint restart/truncate next72/89/93/97 source wrappers, savepoint byte truncation, VFS savepoint rollback, VFS writer/sync/lock/rollback-journal application, B-tree pointer-map/overflow/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is SHM salt/mxFrame current-source validation plus restart/truncate source-transition diagnostics before reset/truncate decisions are trusted.

Dependency closure: no new support component is needed. The slice reuses lane-local WAL parsing/checksum validation, SHM read-mark parsing, durable checkpoint planning, and reader snapshot helpers.

Next task: continue with broader pager/VFS transaction durability or another WAL current-source storage edge that is not another reader-pin wrapper.

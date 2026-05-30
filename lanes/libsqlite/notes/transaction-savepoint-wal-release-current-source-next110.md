# Transaction savepoint WAL release current-source next110

Status: focused PHP behavior growth for `transaction-savepoint-wal-release-current-source-next110`.

This slice adds `SQLiteSavepointStack::releaseCurrentWalSourceAndAppendNextFrame110()` for copied Application import transactions in WAL mode. It validates that the current WAL bytes match the parsed WAL source before trusting release state, releases a nested savepoint into the outer transaction, preserves the released WAL frame prefix, and appends the next transaction frame at the next WAL index without reopening the released savepoint.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteSavepointStack.php`
- `php -l lanes/libsqlite/tests/SQLiteTransactionSavepointWalReleaseCurrentSourceNext110Test.php`
- `php -l lanes/libsqlite/examples/application-transaction-savepoint-wal-release-current-source-next110.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTransactionSavepointWalReleaseCurrentSourceNext110Test.php`
- `php lanes/libsqlite/examples/application-transaction-savepoint-wal-release-current-source-next110.php --self-test`
- `git diff --check -- lanes/libsqlite`

Focused result:

- `1 test files, 45 assertions, 0 failures`
- Application smoke: `application transaction savepoint WAL release current-source next110 self-test passed`

Expected dashboard movement: `phpPass` +45, from `42491` to `42536`. Mapped upstream coverage remains `604 / 1589`; this composes already mapped savepoint/WAL transaction primitives rather than claiming a new upstream inventory row.

Non-overlap: avoids accepted transaction savepoint trigger rollback, WAL checksum/salt recovery, WAL restart/truncate savepoint readers, WAL byte truncation, VFS savepoint rollback application, rollback-journal apply/commit/super-journal clusters, VFS writer/sync/lock clusters, B-tree freeblock/freelist/page-move/root-collapse/overflow clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new behavior is the release-time current WAL source guard plus next-frame append continuity in the still-open outer transaction.

Dependency closure: no new support component is needed. This reuses lane-local `SQLiteSavepointStack` WAL bookkeeping and `SQLiteWal` source serialization/checksum parsing.

Next task: continue with broader pager/VFS transaction application or another non-overlapping WAL durability edge; avoid another savepoint wrapper unless it applies a distinct storage transition.

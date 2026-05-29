# WAL checksum salt recovery current-next70

Status: focused PHP corpus growth for WAL checksum/salt recovery after a WAL restart.

This slice adds `SQLiteWal::checksumSaltRecoveryCurrentNext()`. It composes existing WAL checksum and transaction recovery primitives across the current WAL and the next WAL after a restart. The planner reports salt rotation, committed current/next reader visibility, stale old-salt tail discard, checkpoint database use, and dependency markers for copied WordPress `wp_options` WAL recovery.

Focused verification:

```bash
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteWalChecksumSaltRecoveryCurrentNext70Test.php
php -l lanes/libsqlite/examples/wordpress-wal-checksum-salt-recovery-current-next70.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalChecksumSaltRecoveryCurrentNext70Test.php
php lanes/libsqlite/examples/wordpress-wal-checksum-salt-recovery-current-next70.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass` +53, from 26014 to 26067, from the 53 independent PASS lines in `SQLiteWalChecksumSaltRecoveryCurrentNext70Test.php`.

Non-overlap: this avoids accepted WAL reader-pin restart/truncate handoff, WAL corrupt recovery current/next boundaries, WAL checkpoint transactions, WAL savepoint byte truncation, VFS savepoint/rollback/commit application, rollback-journal and super-journal commit/apply clusters, B-tree page/freelist/overflow clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, VFS writer/sync/lock clusters, and Unicode GLOB behavior. The new surface is current-to-next WAL restart salt rotation with stale old-salt tail recovery.

Dependency closure: no new support component is needed. The slice reuses existing lane-local WAL header parsing, checksum chaining, transaction recovery, checkpoint image, and reader visibility primitives.

Next task: continue with broader WAL/pager durability or VFS application work, especially actual recovery/open wiring that consumes this salt-recovery boundary.

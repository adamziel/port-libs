# WAL SHM Readmark Recovery Current Next70

Status: focused PHP behavior growth for WAL SHM read-mark recovery.

This slice adds `SQLiteShmIndex::recoverReadMarksFromWal()`. It models rebuilding SHM read marks from the current WAL bytes when the wal-index header copy is stale, the SHM salt no longer matches the WAL header, reader locks were abandoned, or the WAL has only an uncommitted tail. Matching WAL salt/header recovery preserves locked current readers through the last committed frame, discards unlocked, beyond-WAL, and uncommitted-tail marks, and reports the next reader frame/checkpoint plan.

Application smoke: `application-wal-shm-readmark-recovery-current-next70.php` reports copied `wp_options` WAL recovery diagnostics, including preserved reader slots, discarded stale slots, next read marks, checkpoint pinning, and dependency tags without requiring ext/sqlite.

Verified PASS delta: +57 focused PASS lines. `lane-status.json` `phpPass` moves from `26014` to `26071`. Mapped upstream coverage remains `464 / 1589` because this is a focused PHP WAL/SHM behavior slice over already mapped WAL read-mark inventory.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteShmIndex.php
php -l lanes/libsqlite/tests/SQLiteWalShmReadMarkRecoveryCurrentNext70Test.php
php -l lanes/libsqlite/examples/application-wal-shm-readmark-recovery-current-next70.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalShmReadMarkRecoveryCurrentNext70Test.php
php lanes/libsqlite/examples/application-wal-shm-readmark-recovery-current-next70.php --self-test
```

Focused result: `1 test files, 57 assertions, 0 failures`.

Non-overlap: this avoids accepted WAL reader-pin restart/truncate handoff, restart checkpoint reader yield, SHM checkpoint restart diagnostics, WAL checksum/corrupt recovery apply, WAL savepoint byte truncation, VFS savepoint rollback, rollback-journal commit/apply, VFS writer/sync/lock clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, B-tree page move/root-collapse/overflow release work, and Unicode GLOB. The new behavior is rebuilding SHM read-mark state from current WAL header/salt and committed-frame boundaries after stale or invalid SHM state.

Dependency closure: no new support component is needed. The slice reuses existing lane-local `SQLiteWal`, WAL checksum/frame parsing, and `SQLiteShmIndex` read-mark checkpoint primitives.

Next task: continue with a non-overlapping WAL/pager durability edge such as checkpoint/reset state application beyond already accepted reader-pin, savepoint, checksum, rollback-journal, and VFS writer/sync clusters.

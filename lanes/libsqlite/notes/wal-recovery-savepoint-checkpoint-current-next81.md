# WAL recovery savepoint checkpoint current-next81

Status: focused PHP behavior growth for the WAL savepoint rollback/release checkpoint boundary.

This slice adds `SQLiteWalSavepointCheckpointPlan::releaseAfterRollbackCheckpointCurrentNext()`. It models a failed Application import batch where `ROLLBACK TO` keeps the savepoint active, `RELEASE` merges the cleared savepoint back into the outer transaction, and a RESTART/TRUNCATE checkpoint persists the retained WAL prefix. The current reader still resolves retained pages from WAL, while the next reader resolves the same images from the checkpointed database after the WAL reset/truncate.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalRecoverySavepointCheckpointCurrentNext81Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 51 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-recovery-savepoint-checkpoint-current-next81.php --self-test
application wal recovery savepoint checkpoint current-next81 self-test passed
```

Dashboard delta: `phpPass` moves from `29984` to `30035` for the 51 verified focused PASS lines. Mapped upstream coverage remains unchanged because this is an additional behavior-backed PHP slice over the existing WAL/savepoint/checkpoint inventory rather than a fresh upstream denominator row.

Non-overlap: this avoids accepted WAL savepoint byte truncation, savepoint page-image rollback, VFS savepoint rollback apply, WAL restart/truncate reader handoff, WAL checkpoint transactions, WAL checksum/salt recovery, WAL SHM readmark recovery, rollback-journal apply/commit/super-journal paths, VFS file writer/sync/lock clusters, B-tree freeblock/freelist/page-move/root-collapse/overflow clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is the explicit release-after-rollback boundary before a checkpoint reset/truncate makes the next reader use the checkpointed database image.

Dependency closure: no new support component is needed. The slice reuses lane-local savepoint rollback/release metadata, WAL frame parsing/checkpointing, and reader snapshot primitives.

Next task: continue with broader pager/VFS transaction durability or WAL checkpoint application around real file handles; avoid another savepoint wrapper unless it applies a distinct pager state transition.

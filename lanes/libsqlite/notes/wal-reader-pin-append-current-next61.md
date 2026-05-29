# WAL reader pin append current next61

Status: focused PHP corpus growth for WAL pinned-reader checkpoint plus writer append current/next behavior.

This slice adds `SQLiteWalAppendPlan::pinnedCheckpointAppendCurrentNext()`. It models a copied WordPress database where an active reader pins an older WAL frame, checkpoint backfills only the allowed prefix and preserves the WAL, a writer appends committed and uncommitted transactions after the preserved sidecar, and current/next readers diverge correctly: the current reader keeps the pinned snapshot while the next reader sees committed append frames and hides the uncommitted tail.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteWalAppendPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteWalAppendPlan.php

php -l lanes/libsqlite/tests/SQLiteWalReaderPinAppendCurrentNext61Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteWalReaderPinAppendCurrentNext61Test.php

php -l lanes/libsqlite/examples/wordpress-wal-reader-pin-append-current-next61.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-wal-reader-pin-append-current-next61.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderPinAppendCurrentNext61Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 68 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-wal-reader-pin-append-current-next61.php
```

Expected dashboard movement: `phpPass` +68, from 22215 to 22283, from the 68 independent PASS lines in `SQLiteWalReaderPinAppendCurrentNext61Test.php`. Mapped upstream denominator is unchanged because this composes already mapped WAL checkpoint, reader snapshot, and append primitives into a narrower current/next behavior.

Non-overlap: avoids accepted WAL savepoint byte truncation, WAL checkpoint transactions, WAL restart/yield reader checkpoints, WAL snapshot/savepoint reader checkpoints, rollback-journal apply/commit, super-journal commit, VFS writer/sync/lock clusters, B-tree page/freelist/overflow clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB. The new behavior is the post-checkpoint writer append that must continue on the preserved WAL while the current reader remains pinned.

Dependency closure: no new support component is needed. The slice reuses existing lane-local WAL checkpoint result, WAL append transaction, reader snapshot, and VFS write-operation planning primitives.

# WordPress multisite savepoint WAL current-next46

Status: focused PHP corpus growth for WordPress multisite savepoint rollback across multiple WAL-backed site databases.

This slice adds `SQLiteWordPressMultisiteSavepointWalPlan::rollbackToAcrossSites()`. It composes existing native PHP savepoint page-image rollback with WAL savepoint recovery for a copied WordPress network import touching `main.sqlite` and attached site databases. The plan reports per-site restored page images, retained/discarded WAL frames, current/next reader matrices, and checkpointability after rollback.

Verification:

```
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWordPressMultisiteSavepointWalCurrentNext46Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 59 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-multisite-savepoint-wal-current-next46.php
```

Non-overlap: this avoids accepted WAL byte truncation-only diagnostics, VFS savepoint rollback application, rollback-journal apply/commit, WAL checkpoint transactions, WAL append/checkpoint-append transaction persistence, VFS writer/sync/lock clusters, B-tree page/freeblock/overflow clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new behavior is multisite current/next savepoint recovery composition across separate WordPress site database images.

Dependency closure: no new support component is needed. The slice reuses lane-local `SQLiteSavepointStack`, `SQLiteWal`, and `SQLiteWalSavepointRecoveryPlan`.

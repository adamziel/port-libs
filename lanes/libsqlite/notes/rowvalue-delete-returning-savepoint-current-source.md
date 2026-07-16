# Row-value DELETE RETURNING savepoint current-source

Status: consolidation follow-up for nested row-value `DELETE ... RETURNING` savepoints.

This pass removes the remaining worker-number surface from the direct row-value DELETE RETURNING savepoint behavior. The canonical `SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan` still models the same Application cleanup batch where an inner released `DELETE RETURNING` savepoint removes copied transient option rows, a later inner delete batch yields attempted rows, and a malformed row-value predicate rolls back only that later savepoint. The current source keeps the released deletes, restores the rolled-back delete rows, and suppresses the rolled-back `RETURNING` rows.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueDeleteReturningSavepointCurrentSourceTest.php
php -l lanes/libsqlite/examples/application-rowvalue-delete-returning-savepoint-current-source.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueDeleteReturningSavepointCurrentSourceTest.php
php lanes/libsqlite/examples/application-rowvalue-delete-returning-savepoint-current-source.php
git diff --check -- lanes/libsqlite
```

Focused test delta: no new behavior assertions; this is a suffix/helper consolidation slice preserving the existing 67 focused assertions in `SQLiteRowValueDeleteReturningSavepointCurrentSourceTest.php`.

Expected dashboard delta: public pass and mapped-coverage counters do not move; this is a cleanup-only consolidation pass.

Non-overlap: this avoids accepted row-value ABORT RETURNING savepoint, row-value DELETE/UPDATE savepoint, row-value conflict/upsert/trigger RETURNING clusters, SELECT SQL text/order/group/subquery clusters, VFS/WAL/pager savepoint application clusters, B-tree, JSON, PRAGMA, and encoding surfaces. The preserved surface is nested `DELETE RETURNING` current-source handling where released delete yields survive a later rolled-back delete savepoint.

Dependency closure: no new support component is needed. The slice reuses the lane-local `SQLiteUpdateDeleteReturningSql` row-value predicate executor and adds bounded savepoint orchestration for copied Application `wp_options` cleanup rows.

Next task: continue retiring numbered row-value/savepoint labels in direct canonical production files without changing behavior.

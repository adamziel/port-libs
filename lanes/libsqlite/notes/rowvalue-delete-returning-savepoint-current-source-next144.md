# Row-value DELETE RETURNING savepoint current-source next144

Status: focused PHP behavior growth for nested row-value `DELETE ... RETURNING` savepoints.

This slice adds `SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan`. It models a WordPress cleanup batch where an inner released `DELETE RETURNING` savepoint removes copied transient option rows, a later inner delete batch yields attempted rows, and a malformed row-value predicate rolls back only that later savepoint. The current source keeps the released deletes, restores the rolled-back delete rows, and suppresses the rolled-back `RETURNING` rows.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueDeleteReturningSavepointCurrentSourceNext144Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-delete-returning-savepoint-current-source-next144.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueDeleteReturningSavepointCurrentSourceNext144Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-delete-returning-savepoint-current-source-next144.php
git diff --check -- lanes/libsqlite
```

Focused test delta: +67 focused assertions in `SQLiteRowValueDeleteReturningSavepointCurrentSourceNext144Test.php`.

Expected dashboard delta: `phpPass` moves from `63412` to `63479`. Mapped upstream coverage remains `606 / 1589`; this is focused PHP behavior over existing row-value DML/savepoint inventory rather than a new mapped upstream row.

Non-overlap: this avoids accepted row-value ABORT RETURNING savepoint next140, row-value DELETE/UPDATE savepoint next141, row-value conflict/upsert/trigger RETURNING clusters, SELECT SQL text/order/group/subquery clusters, VFS/WAL/pager savepoint application clusters, B-tree, JSON, PRAGMA, and encoding surfaces. The new surface is nested `DELETE RETURNING` current-source handling where released delete yields survive a later rolled-back delete savepoint.

Dependency closure: no new support component is needed. The slice reuses the lane-local `SQLiteUpdateDeleteReturningSql` row-value predicate executor and adds bounded savepoint orchestration for copied WordPress `wp_options` cleanup rows.

Next task: continue with broader SQL executor/planner correctness or another non-overlapping current-source row-value DML gap; avoid repeating next140/next141 rollback and mixed update/delete behavior.

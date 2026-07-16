# Row-value delete update savepoint current-source next135

Status: focused PHP behavior growth for `rowvalue-delete-update-savepoint-current-source-next135`.

This slice adds `SQLiteRowValueDeleteUpdateSavepointCurrentSourceNext135Plan`, a bounded native PHP executor for statement-order `DELETE ... RETURNING` and row-value `UPDATE ... RETURNING` inside one savepoint. It proves a DELETE changes the current source seen by the following row-value UPDATE and DELETE, while an aborting later UPDATE rolls the current source back to the savepoint image and leaves attempted next-source diagnostics visible.

Application smoke: `application-rowvalue-delete-update-savepoint-current-source.php` models a copied `wp_options` cleanup/promote batch where a stale transient is deleted, the following row-value UPDATE skips that deleted row, conflict replacement promotes the surviving network transient to `siteurl`, and the final DELETE sees the updated current source.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRowValueDeleteUpdateSavepointCurrentSourceNext135Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueDeleteUpdateSavepointCurrentSourceNext135Plan.php

php -l lanes/libsqlite/tests/SQLiteRowValueDeleteUpdateSavepointCurrentSourcePlanTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueDeleteUpdateSavepointCurrentSourcePlanTest.php

php -l lanes/libsqlite/examples/application-rowvalue-delete-update-savepoint-current-source.php
No syntax errors detected in lanes/libsqlite/examples/application-rowvalue-delete-update-savepoint-current-source.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueDeleteUpdateSavepointCurrentSourcePlanTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 66 assertions, 0 failures

php lanes/libsqlite/examples/application-rowvalue-delete-update-savepoint-current-source.php --self-test
application-rowvalue-delete-update-savepoint-current-source self-test passed
```

Dashboard delta: `phpPass` moves from `56681` to `56747` from 66 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is fresh focused PHP behavior over already mapped row-value DML/savepoint primitives rather than a new manifest-backed upstream row.

Non-overlap: avoids accepted row-value UPDATE/DELETE RETURNING conflict next130, row-value UPSERT savepoint next131, trigger UPSERT savepoint RETURNING, WAL/pager savepoint byte/VFS apply clusters, B-tree overflow/freelist/page-move/root-collapse clusters, JSON table cursor/source/constraint work, SELECT SQL text/group/order/subquery clusters, VFS lock/write/sync clusters, and encoding Unicode GLOB work. The new surface is DELETE-current-source feeding subsequent row-value UPDATE/DELETE statement selection inside one savepoint.

Dependency closure: no new support component is needed. The slice reuses lane-local `SQLiteUpdateDeleteReturningSql` row-value DML execution and adds bounded savepoint composition only.

Next task: continue with distinct SQL executor/planner, pager/VFS transaction application, or another non-overlapping row-value DML edge only if it adds comparable focused behavior coverage.

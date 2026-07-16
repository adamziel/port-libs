# Row-Value Savepoint Consolidation Forty-Eighth Pass

Consolidated the row-value UPDATE/DELETE RETURNING savepoint abort-statement
surface away from worker-numbered exposure. The canonical production
entry point remains descriptive:

- `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackConflict()`

The direct test and Application example now use stable descriptive filenames:

- `SQLiteRowValueAbortStatementSavepointTest.php`
- `application-rowvalue-abort-statement-savepoint.php`

This is consolidation-only cleanup. It does not add behavior, change `phpPass`,
or change mapped upstream coverage.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueAbortStatementSavepointTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-abort-statement-savepoint.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueAbortStatementSavepointTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-abort-statement-savepoint.php --self-test`

Dependency closure: no new support component is needed; this cleanup reuses the
existing native PHP row-value UPDATE/DELETE RETURNING executor and savepoint
current-source retry primitives.

Non-overlap: this pass avoids rowvalue-window, WAL/VFS, JSON table, planner,
trigger, and B-tree behavior surfaces and only removes numbered exposure from
the row-value savepoint abort-statement direct surface.

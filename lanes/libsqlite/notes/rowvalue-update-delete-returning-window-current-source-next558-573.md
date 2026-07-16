# Row-value UPDATE/DELETE RETURNING window current-source next558-573

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
directly after merged next542-557. It adds the same four-step continuation
cadence for next558-573: handoff metadata, current-source hash audit,
throughput preflight, and final ready seals over the existing row-value
UPDATE/DELETE RETURNING window current-source stream.

The Application-shaped example uses copied `wp_options` rows with yielded,
rolled-back attempted, and retried row-value UPDATE/DELETE RETURNING statements.
It verifies next558 starts after the ready next554-557 range and that next561,
next565, next569, and next573 all publish ready seals without touching executor,
WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, lane-status, supervisor,
or private state surfaces.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext558573Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next558-573.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext558573Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next558-573.php --self-test`
- `git diff --check`

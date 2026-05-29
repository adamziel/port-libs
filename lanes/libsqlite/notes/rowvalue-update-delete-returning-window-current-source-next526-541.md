# Row-value UPDATE/DELETE RETURNING window current-source next526-541

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
directly after merged next510-525. It adds the same four-step continuation
cadence for next526-541: handoff metadata, current-source hash audit,
throughput preflight, and final ready seals over the existing row-value
UPDATE/DELETE RETURNING window current-source stream.

The WordPress-shaped example uses copied `wp_options` rows with yielded,
rolled-back attempted, and retried row-value UPDATE/DELETE RETURNING statements.
It verifies next526 starts after the ready next522-525 range and that next529,
next533, next537, and next541 all publish ready seals without touching executor,
WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, lane-status, supervisor,
or private state surfaces.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext526541Test.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next526-541.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext526541Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next526-541.php --self-test`
- `git diff --check`

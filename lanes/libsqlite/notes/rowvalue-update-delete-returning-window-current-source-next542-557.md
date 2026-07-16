# Row-value UPDATE/DELETE RETURNING window current-source next542-557

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
directly after merged next526-541. It adds the same four-step continuation
cadence for next542-557: handoff metadata, current-source hash audit,
throughput preflight, and final ready seals over the existing row-value
UPDATE/DELETE RETURNING window current-source stream.

The Application-shaped example uses copied `wp_options` rows with yielded,
rolled-back attempted, and retried row-value UPDATE/DELETE RETURNING statements.
It verifies next542 starts after the ready next538-541 range and that next545,
next549, next553, and next557 all publish ready seals without touching executor,
WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, lane-status, supervisor,
or private state surfaces.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext542557Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next542-557.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext542557Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next542-557.php --self-test`
- `git diff --check`

# Row-value UPDATE/DELETE RETURNING window current-source next574-589

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
directly after merged next558-573. It adds the same four-step continuation
cadence for next574-589: handoff metadata, current-source hash audit,
throughput preflight, and final ready seals over the existing row-value
UPDATE/DELETE RETURNING window current-source stream.

The Application-shaped example uses copied `wp_options` rows with yielded,
rolled-back attempted, and retried row-value UPDATE/DELETE RETURNING statements.
It verifies next574 starts after the ready next570-573 range and that next577,
next581, next585, and next589 all publish ready seals without touching executor,
WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, lane-status, supervisor,
or private state surfaces.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext574589Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next574-589.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext574589Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next574-589.php --self-test`
- `git diff --check`

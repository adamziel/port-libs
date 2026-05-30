# Row-value UPDATE/DELETE RETURNING window current-source next590-605

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
directly after merged next574-589. It keeps the established canonical source
class because the local pattern already centralizes these numbered row-value
RETURNING window continuations there; a new numbered source class is not needed.

The next590-605 range repeats the existing four-step cadence: handoff metadata,
current-source hash audit, throughput preflight, and final ready seals over the
existing row-value UPDATE/DELETE RETURNING window current-source stream.

The Application-shaped example uses copied `wp_options` rows with yielded,
rolled-back attempted, and retried row-value UPDATE/DELETE RETURNING statements.
It verifies next590 starts after the ready next586-589 range and that next593,
next597, next601, and next605 all publish ready seals without touching executor,
WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, lane-status, supervisor,
or private state surfaces.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext590605Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next590-605.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext590605Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next590-605.php --self-test`
- `git diff --check`

# Row-value UPDATE/DELETE RETURNING window current-source next622-637

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
directly after merged next606-621. It keeps the established canonical source
class because the local pattern already centralizes these numbered row-value
RETURNING window continuations there; a new numbered source class is not needed.

The next622-637 range repeats the existing four-step cadence: handoff metadata,
current-source hash audit, throughput preflight, and final ready seals over the
existing row-value UPDATE/DELETE RETURNING window current-source stream.

The WordPress-shaped example uses copied `wp_options` rows with yielded,
rolled-back attempted, and retried row-value UPDATE/DELETE RETURNING statements.
It verifies next622 starts after the ready next618-621 range and that next625,
next629, next633, and next637 all publish ready seals without touching executor,
WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, lane-status, supervisor,
or private state surfaces.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext622637Test.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next622-637.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext622637Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next622-637.php --self-test`
- `git diff --check`

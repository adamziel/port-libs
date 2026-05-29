# Row-value UPDATE/DELETE RETURNING window current-source next606-621

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
directly after merged next590-605. It keeps the established canonical source
class because the local pattern already centralizes these numbered row-value
RETURNING window continuations there; a new numbered source class is not needed.

The next606-621 range repeats the existing four-step cadence: handoff metadata,
current-source hash audit, throughput preflight, and final ready seals over the
existing row-value UPDATE/DELETE RETURNING window current-source stream.

The WordPress-shaped example uses copied `wp_options` rows with yielded,
rolled-back attempted, and retried row-value UPDATE/DELETE RETURNING statements.
It verifies next606 starts after the ready next602-605 range and that next609,
next613, next617, and next621 all publish ready seals without touching executor,
WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, lane-status, supervisor,
or private state surfaces.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext606621Test.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next606-621.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext606621Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next606-621.php --self-test`
- `git diff --check`

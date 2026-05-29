# rowvalue-update-delete-returning-window-current-source-next398-413

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
with `executeNext398()` through `executeNext413()` as a direct continuation of
the merged next382-397 row-value UPDATE/DELETE RETURNING window current-source
seal.

The new range keeps the existing four-step cadence: handoff, source audit,
throughput preflight counters, and final ready seals for next398-401,
next402-405, next406-409, and next410-413.

WordPress smoke: `wordpress-rowvalue-returning-window-current-source-next398-413.php`
uses copied `wp_options` fixture rows with row-value UPDATE and DELETE RETURNING
streams to verify every candidate status plus the ready seals after next397.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next398-413.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext398413Test.php`
- `php tools/run-tests.php SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext398413Test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next398-413.php --self-test`
- `git diff --check`

Non-overlap: this only adds next398-413 wrappers, focused example/test coverage,
and this note. It does not touch progress files, porting summaries, lane-status
state, supervisor state, broad suite evidence, executor internals, WAL/VFS, JSON
table, planner, B-tree, PRAGMA, trigger, or unrelated private state.

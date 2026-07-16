# rowvalue-update-delete-returning-window-current-source-next414-429

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
with `executeNext414()` through `executeNext429()` as a direct continuation of
the merged next398-413 row-value UPDATE/DELETE RETURNING window current-source
seal.

The range keeps the four-step cadence: handoff, source audit, throughput
preflight counters, and final ready seals for next414-417, next418-421,
next422-425, and next426-429.

Application smoke: `application-rowvalue-returning-window-current-source-next414-429.php`
uses copied `wp_options` fixture rows with row-value UPDATE and DELETE RETURNING
streams to verify every candidate status plus the ready seals after next413.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next414-429.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext414429Test.php`
- `php tools/run-tests.php SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext414429Test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next414-429.php --self-test`
- `git diff --check`

Non-overlap: this only adds next414-429 wrappers, focused example/test coverage,
and this note. It does not touch progress files, porting summaries, lane-status
state, supervisor state, broad suite evidence, executor internals, WAL/VFS, JSON
table, planner, B-tree, PRAGMA, trigger, or unrelated private state.

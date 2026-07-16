# rowvalue-update-delete-returning-window-current-source-next494-509

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
with `executeNext494()` through `executeNext509()` as a direct continuation of
the merged next478-493 row-value UPDATE/DELETE RETURNING window current-source
seal.

The range keeps the four-step cadence: handoff, source audit, throughput
preflight counters, and final ready seals for next494-497, next498-501,
next502-505, and next506-509.

Application smoke: `application-rowvalue-returning-window-current-source-next494-509.php`
uses copied `wp_options` fixture rows with row-value UPDATE and DELETE RETURNING
streams to verify every candidate status plus the ready seals after next493.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next494-509.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext494509Test.php`
- `php tools/run-tests.php SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext494509Test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next494-509.php --self-test`
- `git diff --check`

Non-overlap: this only adds next494-509 wrappers, focused example/test coverage,
and this note. It does not touch progress files, porting summaries, lane-status
state, supervisor state, broad suite evidence, executor internals, WAL/VFS, JSON
table, planner, B-tree, PRAGMA, trigger, or unrelated private state.

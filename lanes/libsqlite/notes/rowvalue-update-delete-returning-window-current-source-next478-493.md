# rowvalue-update-delete-returning-window-current-source-next478-493

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
with `executeNext478()` through `executeNext493()` as a direct continuation of
the merged next462-477 row-value UPDATE/DELETE RETURNING window current-source
seal.

The range keeps the four-step cadence: handoff, source audit, throughput
preflight counters, and final ready seals for next478-481, next482-485,
next486-489, and next490-493.

Application smoke: `application-rowvalue-returning-window-current-source-next478-493.php`
uses copied `wp_options` fixture rows with row-value UPDATE and DELETE RETURNING
streams to verify every candidate status plus the ready seals after next477.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next478-493.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext478493Test.php`
- `php tools/run-tests.php SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext478493Test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next478-493.php --self-test`
- `git diff --check`

Non-overlap: this only adds next478-493 wrappers, focused example/test coverage,
and this note. It does not touch progress files, porting summaries, lane-status
state, supervisor state, broad suite evidence, executor internals, WAL/VFS, JSON
table, planner, B-tree, PRAGMA, trigger, or unrelated private state.

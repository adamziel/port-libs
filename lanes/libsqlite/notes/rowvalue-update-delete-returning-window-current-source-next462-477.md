# rowvalue-update-delete-returning-window-current-source-next462-477

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
with `executeNext462()` through `executeNext477()` as a direct continuation of
the merged next446-461 row-value UPDATE/DELETE RETURNING window current-source
seal.

The range keeps the four-step cadence: handoff, source audit, throughput
preflight counters, and final ready seals for next462-465, next466-469,
next470-473, and next474-477.

Application smoke: `application-rowvalue-returning-window-current-source-next462-477.php`
uses copied `wp_options` fixture rows with row-value UPDATE and DELETE RETURNING
streams to verify every candidate status plus the ready seals after next461.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next462-477.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext462477Test.php`
- `php tools/run-tests.php SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext462477Test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next462-477.php --self-test`
- `git diff --check`

Non-overlap: this only adds next462-477 wrappers, focused example/test coverage,
and this note. It does not touch progress files, porting summaries, lane-status
state, supervisor state, broad suite evidence, executor internals, WAL/VFS, JSON
table, planner, B-tree, PRAGMA, trigger, or unrelated private state.

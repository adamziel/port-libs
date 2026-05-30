# rowvalue-update-delete-returning-window-current-source-next430-445

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
with `executeNext430()` through `executeNext445()` as a direct continuation of
the merged next414-429 row-value UPDATE/DELETE RETURNING window current-source
seal.

The range keeps the four-step cadence: handoff, source audit, throughput
preflight counters, and final ready seals for next430-433, next434-437,
next438-441, and next442-445.

Application smoke: `application-rowvalue-returning-window-current-source-next430-445.php`
uses copied `wp_options` fixture rows with row-value UPDATE and DELETE RETURNING
streams to verify every candidate status plus the ready seals after next429.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next430-445.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext430445Test.php`
- `php tools/run-tests.php SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext430445Test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next430-445.php --self-test`
- `git diff --check`

Non-overlap: this only adds next430-445 wrappers, focused example/test coverage,
and this note. It does not touch progress files, porting summaries, lane-status
state, supervisor state, broad suite evidence, executor internals, WAL/VFS, JSON
table, planner, B-tree, PRAGMA, trigger, or unrelated private state.

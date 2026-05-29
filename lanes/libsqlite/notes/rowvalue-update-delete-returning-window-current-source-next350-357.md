# rowvalue-update-delete-returning-window-current-source-next350-357

Status: focused PHP behavior growth for the row-value UPDATE/DELETE RETURNING
window current-source continuation after the merged next342-349 chain.

This slice adds `executeNext350()` through `executeNext357()` to
`SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`. The new
methods add two continuation blocks over the existing row-value
UPDATE/DELETE RETURNING window current-source plan: handoff metadata,
current/next source hash auditing, throughput preflight counters, and final
ready seals for next350-353 and next354-357.

WordPress smoke: `wordpress-rowvalue-returning-window-current-source-next350-357.php`
models copied `wp_options` UPDATE and DELETE RETURNING phases that yield,
roll back attempted rows, retry from the current source, and verify that both
new ready seals preserve the current-source handoff.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next350-357.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext350357Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext350357Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next350-357.php --self-test`

Expected dashboard movement: `phpPass +1` from the focused test file.
`benchmarkDenominator.mapped` remains unchanged; this is current-source PHP
metadata over already mapped row-value DML, RETURNING, savepoint, and window
inventory.

Non-overlap: avoids accepted next342-349 handoff, row-value parser/executor
changes, savepoint conflict variants, trigger RETURNING, WAL/VFS, JSON table,
planner, B-tree, PRAGMA, suite-runner files, lane-status files, supervisor
state, and unrelated private state. The new behavior is specifically the
next350-357 continuation seal after the merged next342-349 chain.

Dependency closure: no new support component is needed; this reuses native
PHP row-value UPDATE/DELETE RETURNING execution, current-source window phase
rows, and the established handoff/source-audit/preflight/seal metadata shape.

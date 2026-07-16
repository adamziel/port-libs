# rowvalue-update-delete-returning-window-current-source-next374-381

Status: focused PHP behavior growth for the row-value UPDATE/DELETE RETURNING
window current-source continuation after the merged next366-373 chain.

This slice adds `executeNext374()` through `executeNext381()` to
`SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`. The new
methods add two continuation blocks over the existing row-value
UPDATE/DELETE RETURNING window current-source plan: handoff metadata,
current/next source hash auditing, throughput preflight counters, and final
ready seals for next374-377 and next378-381.

Application smoke: `application-rowvalue-returning-window-current-source-next374-381.php`
models copied `wp_options` UPDATE and DELETE RETURNING phases that yield,
roll back attempted rows, retry from the current source, and verify that both
new ready seals preserve the current-source handoff.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next374-381.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext374381Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext374381Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next374-381.php --self-test`

Expected dashboard movement: `phpPass +1` from the focused test file.
`benchmarkDenominator.mapped` remains unchanged; this is current-source PHP
metadata over already mapped row-value DML, RETURNING, savepoint, and window
inventory.

Non-overlap: avoids accepted next366-373 handoff, row-value parser/executor
changes, savepoint conflict variants, trigger RETURNING, WAL/VFS, JSON table,
planner, B-tree, PRAGMA, suite-runner files, lane-status files, supervisor
state, and unrelated private state. The new behavior is specifically the
next374-381 continuation seal after the merged next366-373 chain.

Dependency closure: no new support component is needed; this reuses native
PHP row-value UPDATE/DELETE RETURNING execution, current-source window phase
rows, and the established handoff/source-audit/preflight/seal metadata shape.

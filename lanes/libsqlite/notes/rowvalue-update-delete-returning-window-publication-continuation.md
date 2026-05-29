# rowvalue-update-delete-returning-window-publication-continuation

Status: focused PHP behavior growth for the row-value UPDATE/DELETE RETURNING
window current-source continuation after the merged next342-349 chain.

This slice renames the public tail continuation methods to
`executeAfterCurrentSealHandoff()` through `executePublicationSeal()` in
`SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`. The new
methods add two continuation blocks over the existing row-value
UPDATE/DELETE RETURNING window current-source plan: handoff metadata,
current/next source hash auditing, throughput preflight counters, and final
ready seals for the after-current and publication phases.

WordPress smoke: `wordpress-rowvalue-returning-window-publication-continuation.php`
models copied `wp_options` UPDATE and DELETE RETURNING phases that yield,
roll back attempted rows, retry from the current source, and verify that both
new ready seals preserve the current-source handoff.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-publication-continuation.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowPublicationContinuationTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowPublicationContinuationTest.php`: `1 test files, 18 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-publication-continuation.php --self-test`: self-test passed.

Expected dashboard movement: `phpPass +1` from the focused test file.
`benchmarkDenominator.mapped` remains unchanged; this is current-source PHP
metadata over already mapped row-value DML, RETURNING, savepoint, and window
inventory.

Non-overlap: avoids accepted next342-349 handoff, row-value parser/executor
changes, savepoint conflict variants, trigger RETURNING, WAL/VFS, JSON table,
planner, B-tree, PRAGMA, suite-runner files, lane-status files, supervisor
state, and unrelated private state. The new behavior is specifically the
publication continuation seal after the merged next342-349 chain.

Dependency closure: no new support component is needed; this reuses native
PHP row-value UPDATE/DELETE RETURNING execution, current-source window phase
rows, and the established handoff/source-audit/preflight/seal metadata shape.

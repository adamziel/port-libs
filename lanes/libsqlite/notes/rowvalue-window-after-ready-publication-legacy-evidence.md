# Row-value Window After-ready Publication Legacy Evidence

Status: superseded focused PHP behavior growth for the row-value
UPDATE/DELETE RETURNING window current-source continuation after the merged
previous chain.

The production API for this slice has been consolidated into descriptive
after-ready publication methods on
`SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`. Those
methods preserve two continuation blocks over the existing row-value
UPDATE/DELETE RETURNING window current-source plan: handoff metadata,
current/next source hash auditing, throughput preflight counters, and final
ready seals.

WordPress smoke: `wordpress-rowvalue-returning-window-after-ready-publication.php`
models copied `wp_options` UPDATE and DELETE RETURNING phases that yield,
roll back attempted rows, retry from the current source, and verify that both
new ready seals preserve the current-source handoff.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-after-ready-publication.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowAfterReadyPublicationTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowAfterReadyPublicationTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-after-ready-publication.php --self-test`

Expected dashboard movement: `phpPass +1` from the focused test file.
`benchmarkDenominator.mapped` remains unchanged; this is current-source PHP
metadata over already mapped row-value DML, RETURNING, savepoint, and window
inventory.

Non-overlap: avoids accepted previous handoff, row-value parser/executor
changes, savepoint conflict variants, trigger RETURNING, WAL/VFS, JSON table,
planner, B-tree, PRAGMA, suite-runner files, lane-status files, supervisor
state, and unrelated private state. The new behavior is specifically the
after-ready publication continuation seal after the merged previous chain.

Dependency closure: no new support component is needed; this reuses native
PHP row-value UPDATE/DELETE RETURNING execution, current-source window phase
rows, and the established handoff/source-audit/preflight/seal metadata shape.

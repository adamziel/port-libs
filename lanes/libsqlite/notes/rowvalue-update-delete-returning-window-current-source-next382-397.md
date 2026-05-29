# rowvalue-update-delete-returning-window-current-source-next382-397

Status: focused PHP behavior growth for the row-value UPDATE/DELETE RETURNING
window current-source continuation after merged next374-381.

This slice adds `executeNext382()` through `executeNext397()` to
`SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`. The new
methods extend the same continuation shape as next374-381 across four
additional blocks: handoff metadata, current/next source hash auditing,
throughput preflight counters, and final ready seals for next382-385,
next386-389, next390-393, and next394-397.

WordPress smoke: `wordpress-rowvalue-returning-window-current-source-next382-397.php`
models copied `wp_options` UPDATE and DELETE RETURNING phases that yield,
roll back attempted rows, retry from the current source, and verify the four
new ready seals preserve the current-source handoff.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next382-397.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext382397Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext382397Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next382-397.php --self-test`

Expected dashboard movement: `phpPass +1` from the focused test file.
`benchmarkDenominator.mapped` remains unchanged; this is current-source PHP
metadata over already mapped row-value DML, RETURNING, savepoint, and window
inventory.

Non-overlap: avoids accepted next374-381 handoff, row-value parser/executor
changes, savepoint conflict variants, trigger RETURNING, WAL/VFS, JSON table,
planner, B-tree, PRAGMA, suite-runner files, lane-status files, supervisor
state, and unrelated private state. The new behavior is specifically the
next382-397 continuation seal after the merged next374-381 chain.

Dependency closure: no new support component is needed; this reuses native
PHP row-value UPDATE/DELETE RETURNING execution, current-source window phase
rows, and the established handoff/source-audit/preflight/seal metadata shape.

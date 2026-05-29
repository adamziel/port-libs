# rowvalue-update-delete-returning-window-current-source-next290-293

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING current-source statement windows after savepoint rollback and retry.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Plan`. It reuses the lane-local row-value UPDATE/DELETE RETURNING executor, records RETURNING rows generated before `ROLLBACK TO`, restores the savepoint image, retries UPDATE/DELETE statements, and emits deterministic current-source window receipts both over the full retry stream and per retry statement partition.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Test.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next290-293.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next290-293.php`
- `git diff --check`

Non-overlap: avoids accepted rowvalue next219 negative LIMIT/OFFSET, next224 and next230 nested savepoint releases, next231 compound tuple sources, next289 all-stream window receipts, JSON table next285-288/289-300 work, WAL/VFS, planner, trigger, suite-countability, and B-tree clusters. The behavior surface is statement-partitioned RETURNING-window receipt stability across current-source rollback and retry.

Dependency closure: no new support component is needed; this uses existing row-array UPDATE/DELETE RETURNING execution and computes row-number/lag/lead style receipts from final current-source RETURNING streams without changing the executor.

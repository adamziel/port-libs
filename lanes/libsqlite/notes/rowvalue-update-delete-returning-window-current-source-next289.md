# rowvalue-update-delete-returning-window-current-source-next289

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING current-source windows after savepoint rollback and retry.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext289`. It reuses the lane-local row-value UPDATE/DELETE RETURNING executor, records RETURNING rows generated before `ROLLBACK TO`, restores the savepoint image, retries UPDATE/DELETE statements, and emits deterministic current-source window receipts over the yielded retry stream.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext289.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next289.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next289.php`
- `git diff --check`

Non-overlap: avoids accepted rowvalue next219 negative LIMIT/OFFSET, next224 and next230 nested savepoint releases, next231 compound tuple sources, JSON table next285-288/289-300 work, WAL/VFS, planner, trigger, suite-countability, and B-tree clusters. The behavior surface is RETURNING-window receipt stability across current-source rollback and retry.

Dependency closure: no new support component is needed; this uses existing row-array UPDATE/DELETE RETURNING execution and computes row-number/lag/lead style receipts from the final current-source RETURNING stream.

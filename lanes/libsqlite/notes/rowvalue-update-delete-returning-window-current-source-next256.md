# rowvalue-update-delete-returning-window-current-source-next256

Status: focused PHP behavior growth for current-source row-value `UPDATE`/`DELETE ... RETURNING` window execution.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext256Plan`. It layers a retry commit-token watermark above the accepted next253 current-window chunk gate: current-source window rows remain durable, but next-source retry RETURNING rows are marked durable only when every retry cursor token is acknowledged after current chunks have completed.

Application path: `application-rowvalue-returning-window-current-source-next256.php` models copied `wp_options` import batches where retry RETURNING rows from the next source must not be committed as migration progress until current-source row-value windows and retry cursor tokens have both been acknowledged.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext256Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext256Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next256.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext256Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next256.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +71` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped row-value DML, RETURNING, savepoint retry, and window inventory.

Dependency closure: no new support component is needed; this reuses native PHP row-value UPDATE/DELETE RETURNING execution, current-source window chunking from next253, and retry cursor tokens.

Non-overlap: avoids accepted next253 cursor/chunk construction, next249 chunking, next248 publication cursor, next245 raw yield-ticket admission, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces. The new surface is retry commit-token durability after current-source admission.

# rowvalue-update-delete-returning-window-current-source-next253

Status: focused PHP behavior growth for current-source row-value `UPDATE`/`DELETE ... RETURNING` window execution.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan`. It layers a chunk-token source gate over the accepted next249 yielded window chunks: next-source retry windows stay hidden until the current-source yield tickets and the derived current-window chunk tokens are both complete. The cursor exposes current chunk rows first, then retry rows only after the chunk source is acknowledged.

WordPress path: `wordpress-rowvalue-returning-window-current-source-next253.php` models copied `wp_options` import batches where yielded current-source row-value RETURNING windows must be acknowledged before retry rows from the next source can publish migration progress.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Test.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next253.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next253.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +65` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped row-value DML, RETURNING, savepoint retry, and window inventory.

Dependency closure: no new support component is needed; this reuses native PHP row-value UPDATE/DELETE RETURNING execution, current-source window chunking from next249, and retry window rows.

Non-overlap: avoids accepted next249 chunk construction, next248 publication cursor, next245 raw yield-ticket admission, next236 current-row frame receipts, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces. The new surface is chunk-token current-source admission before next-source retry window publication.

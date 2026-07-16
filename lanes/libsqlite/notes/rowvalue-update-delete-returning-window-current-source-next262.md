# rowvalue-update-delete-returning-window-current-source-next262

Status: focused PHP behavior growth for `rowvalue-update-delete-returning-window-current-source-next262`.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext262Plan`. It extends the accepted next260 frame-boundary chain with GROUPS/RANGE-style peer admission for row-value `UPDATE`/`DELETE ... RETURNING` windows. A retry row whose peer value also appears in the current-source stream is held until the whole source-crossing peer group is acknowledged, so a copied `wp_options` import cannot expose retry-source peer rows before matching current-source DELETE RETURNING peers have drained.

Application smoke: `application-rowvalue-returning-window-current-source-next262.php` models copied `wp_options` yield, rollback, retry, and DELETE RETURNING rows where `status='stale'` peers span current row `3` and retry row `4`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext262Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext262Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next262.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext262Test.php`
  - Result: `1 test files, 62 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next262.php`
  - Result: `application-rowvalue-returning-window-current-source-next262 self-test passed`

Expected dashboard movement: `phpPass +62` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped row-value DML, RETURNING, savepoint retry, and window inventory.

Dependency closure: no new support component is needed; next262 reuses native PHP row-value UPDATE/DELETE RETURNING window rows, next260 frame-boundary receipts, and native source epochs while adding GROUPS/RANGE peer-group admission across current and retry sources.

Non-overlap: avoids accepted next260 adjacent frame-boundary receipts, next259 CURRENT ROW frame close, next256 commit watermarks, next255 next-row admission, row-value savepoint-only variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces. The narrower behavior is source-crossing peer-group admission for RETURNING window peers.

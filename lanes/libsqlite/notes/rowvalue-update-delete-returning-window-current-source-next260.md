# rowvalue-update-delete-returning-window-current-source-next260

Status: focused PHP behavior growth for `rowvalue-update-delete-returning-window-current-source-next260`.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext260Plan`. It extends the accepted row-value `UPDATE`/`DELETE ... RETURNING` window chain after next255 by adding a frame-source boundary receipt: rows whose preceding/current/following RETURNING window frame crosses from current-source output into retry/next-source output are publishable only after the boundary ticket is acknowledged. That catches the final current-source row and first retry-source row in copied `wp_options` import streams.

Application smoke: `application-rowvalue-returning-window-current-source-next260.php` models copied `wp_options` yield, rollback, retry, and DELETE RETURNING rows where the mixed frame boundary is owned by option IDs `3` and `9` before next-source rows can be exposed.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext260Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext260Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next260.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext260Test.php`
  - Result: `1 test files, 67 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next260.php`
  - Result: `application-rowvalue-returning-window-current-source-next260 self-test passed`

Dashboard delta: +67 focused PASS assertions for libsqlite PHP coverage. No mapped upstream inventory unit is claimed.

Dependency closure: no new support component is needed; next260 reuses native PHP row-value UPDATE/DELETE RETURNING window rows, next251 source epochs, and next255 next-row admission while adding a frame-source boundary receipt.

Non-overlap: this avoids accepted next255 next-row acknowledgement alone, next254 row receipts, next251 epoch/digest fencing, next248 publication cursors, next245 yield gates, row-value savepoint-only variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces. The narrower behavior is the current-source to retry-source RETURNING frame boundary.

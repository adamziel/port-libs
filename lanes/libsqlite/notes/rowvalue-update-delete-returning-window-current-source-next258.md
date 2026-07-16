# rowvalue-update-delete-returning-window-current-source-next258

Implemented `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext258Plan`, a transition-token admission fence layered after accepted next252 row-number/high-water window fencing for row-value `UPDATE`/`DELETE ... RETURNING`.

The new behavior keeps current-source RETURNING window rows publishable while retry rows from the next source remain quarantined until the caller acknowledges a transition token derived from the current high-water ticket, first retry ticket, window ordinals, and window digest. A missing or unexpected transition token suppresses next-source retry publication without repeating the accepted next248 cursor barrier or next252 row-number fence.

Application path: `application-rowvalue-returning-window-current-source-next258.php` models copied `wp_options` cleanup/import rows where current-source returned rows must be checkpointed before retry rows from the next source are exposed.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext258Test.php`
  - Result: `1 test files, 57 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext258Plan.php`
  - Result: `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext258Test.php`
  - Result: `No syntax errors detected`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next258.php`
  - Result: `No syntax errors detected`

Expected dashboard movement: `phpPass +57` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged because this is current-source PHP behavior over already mapped row-value DML, RETURNING, savepoint retry, and window inventory.

Dependency closure: no new support component is needed; this reuses native PHP row-value UPDATE/DELETE RETURNING execution, next248 publication cursors, and next252 current-source window high-water rows.

Non-overlap: avoids accepted next252 row-number/high-water fences, next248 publication cursor barriers, next245 yield-ticket admission, next244 transition windows, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces. The narrower surface is transition-token acknowledgement for admitting next-source retry rows after current-source window high-water publication.

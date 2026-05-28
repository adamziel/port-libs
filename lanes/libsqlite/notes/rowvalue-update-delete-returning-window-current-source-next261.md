# rowvalue-update-delete-returning-window-current-source-next261

Implemented `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext261Plan`, a current-source source-window watermark wrapper over accepted next254 row-value `UPDATE`/`DELETE ... RETURNING` window row receipt admission.

The new behavior computes separate current and next source segment watermarks from admitted row tickets, source epochs, rowids, window frame tokens, running bytes, following bytes, and next254 admission tokens. Next-source retry rows remain held unless the current and next segment watermarks match, preventing copied WordPress option imports from publishing retry `RETURNING` window rows against a stale current-source window segment.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext261Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext261Test.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next261.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext261Test.php`
  - Result: `1 test files, 57 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next261.php --self-test`
  - Result: `wordpress-rowvalue-returning-window-current-source-next261 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +57` from the new focused test file. Mapped upstream coverage is unchanged; this is current-source PHP behavior over already mapped row-value DML, RETURNING, savepoint retry, source handoff, row receipt, and window inventory.

Dependency closure: no new support component is needed; next261 reuses native PHP row-value UPDATE/DELETE RETURNING execution, next248 publication sequencing, next251 source epoch/digest handoff rows, and next254 row-level window receipt admission.

Non-overlap: avoids accepted next254 row receipt matching, next251 digest/epoch handoff, next248 resumable publication cursors, next245 yield-ticket gates, next244 transition windows, savepoint-only row-value RETURNING, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces. The narrower surface is current/next source segment watermark admission after row-level window receipts.

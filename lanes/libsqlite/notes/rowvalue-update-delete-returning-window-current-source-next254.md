# rowvalue-update-delete-returning-window-current-source-next254

Implemented `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext254Plan`, a current-source admission wrapper over accepted next251 row-value `UPDATE`/`DELETE ... RETURNING` window source handoff behavior.

The new behavior requires a per-row receipt keyed to the handoff ticket, source epoch, RETURNING window frame token, rowid, running bytes, and following bytes before current/next rows are admitted for publication. Missing or stale receipts hold the current-source boundary, and copied Application option imports only expose retry rows after the window receipts match the next-source handoff.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext254Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueRowReceiptAdmissionWindowTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-row-receipt-admission-window.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueRowReceiptAdmissionWindowTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-row-receipt-admission-window.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +52` from the new focused test file. Mapped upstream coverage is unchanged; this is current-source PHP behavior over already mapped row-value DML, RETURNING, savepoint retry, source handoff, and window inventory.

Dependency closure: no new support component is needed; next254 reuses native PHP row-value UPDATE/DELETE RETURNING execution, next248 publication sequencing, next251 source epoch/digest handoff rows, and adds bounded row-level window receipt admission.

Non-overlap: avoids accepted next251 digest/epoch handoff, next248 resumable publication cursors, next245 yield-ticket gates, next244 transition windows, savepoint-only row-value RETURNING, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces. The narrower surface is row-level window receipt admission after next251 source handoff.

# rowvalue-update-delete-returning-window-current-source-next248

Implemented `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext248Plan`, a current-source publication cursor barrier over existing row-value `UPDATE`/`DELETE ... RETURNING` window execution.

The new behavior keeps current-source yielded RETURNING rows first in the publication sequence, holds next-source retry rows when a yield ticket is missing or unexpected, and exposes resumable retry rows only after the current-source ticket set is complete.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext248Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next248.php`

Expected dashboard movement: `phpPass +59` from the new focused test file. `benchmarkDenominator.mapped` is unchanged; this is current-source PHP behavior over already mapped row-value DML, RETURNING, savepoint retry, and window inventory.

Non-overlap: avoids accepted next245 yield-ticket gate, next244 transition windows, next241 current-row frames, next236 receipts, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces. The new surface is a resumable publication cursor after current-source row-value RETURNING window yield completion.

Dependency closure: no new support component is needed; this reuses native PHP row-value UPDATE/DELETE RETURNING execution, current-source window receipts, and next245 yield tickets.

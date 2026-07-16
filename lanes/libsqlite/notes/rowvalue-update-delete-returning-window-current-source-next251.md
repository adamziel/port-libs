# rowvalue-update-delete-returning-window-current-source-next251

Implemented `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext251Plan`, a source epoch/digest handoff fence over existing row-value `UPDATE`/`DELETE ... RETURNING` window publication sequencing.

The new behavior keeps current-source yielded RETURNING window rows visible, exposes next-source retry rows only when the next248 publication barrier is open, and additionally requires current/next source digests to match the handoff watermarks. Digest mismatch or missing current-source yield acknowledgements hold retry rows out of the handoff stream.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext251Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next251.php`

Expected dashboard movement: `phpPass +56` from the new focused test file. `benchmarkDenominator.mapped` is unchanged; this is current-source PHP behavior over already mapped row-value DML, RETURNING, savepoint retry, current-source publication, and window inventory.

Non-overlap: avoids accepted next248 resumable publication sequencing, next245 yield-ticket gates, next244 transition windows, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces. The narrower surface is a current/next source epoch and digest watermark before retry rows are handed off.

Dependency closure: no new support component is needed; this reuses native PHP row-value UPDATE/DELETE RETURNING execution, current-source window receipts, next245 yield tickets, and next248 publication sequencing.

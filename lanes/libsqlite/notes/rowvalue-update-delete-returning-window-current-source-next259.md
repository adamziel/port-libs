# rowvalue-update-delete-returning-window-current-source-next259

Status: focused PHP behavior growth for row-value `UPDATE` / `DELETE RETURNING` rows when a `CURRENT ROW` style window frame must close before the following current/next source row is admitted.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext259Plan`. It layers on the accepted next251 source handoff and next255 next-row admission rows, then computes per-ticket frame receipts with previous/current/next ticket boundaries, source-epoch transition detection, and optional previous-frame closure enforcement. The Application path models copied `wp_options` import retries where yielded current-source RETURNING rows must be consumed before the next-source retry rows become visible.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext259Test.php`
- Result: `1 test files, 74 assertions, 0 failures`
- PASS lines: `74`

Application smoke:

- `php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-window-current-source-next259.php --self-test`
- Result: `application-rowvalue-update-delete-returning-window-current-source-next259 self-test passed`

Expected dashboard movement: `phpPass +74` from the new focused test file. Mapped upstream coverage remains `674 / 1589`; this is current-source PHP behavior over already mapped row-value UPDATE/DELETE RETURNING and window inventory rather than a new manifest-backed upstream row.

Non-overlap: avoids accepted next255 next-row admission, next254 receipt validation, next251 source digest handoff, next248 publication cursor sequencing, next245 yield gates, row-value savepoint variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces. The new surface is specifically the `CURRENT ROW` frame-close fence between adjacent RETURNING rows across current-source and next-source epochs.

Dependency closure: no new support component is needed; this reuses lane-local row-value DML execution, RETURNING row publication, source-handoff, and window ticket primitives.

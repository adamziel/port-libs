# rowvalue-update-delete-returning-window-current-source-next255

Status: focused PHP behavior growth for `rowvalue-update-delete-returning-window-current-source-next255`.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext255Plan`. It extends the accepted row-value UPDATE/DELETE RETURNING window chain after the next251 source epoch/digest handoff by adding next-row admission receipts: a RETURNING row is resumable only after its own ticket and the preceding RETURNING ticket are acknowledged. That keeps the retry-source WordPress option rows fenced until current-source delivery has advanced in order.

WordPress smoke: `wordpress-rowvalue-returning-window-current-source-next255.php` covers copied `wp_options` yield, rollback, retry, and DELETE RETURNING rows where five retry-source rows become visible only after ordered next-row ticket acknowledgement.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext255Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext255Test.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next255.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext255Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next255.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +64` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped row-value, RETURNING, savepoint, and window inventory.

Non-overlap: this avoids accepted next250 EXCLUDE TIES, next251 source epoch/digest handoff, next248 resumable publication, next245 yield gates, next232-next247 window frame variants, row-value savepoint retry variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces. The new behavior is next-row admission based on ordered RETURNING ticket acknowledgement after the current-source handoff.

Dependency closure: no new support component is needed; this reuses lane-local row-value UPDATE/DELETE RETURNING execution, source handoff rows, and window cursor ticket metadata.

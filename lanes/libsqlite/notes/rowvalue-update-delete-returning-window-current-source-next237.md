# rowvalue update/delete RETURNING window current-source next237

Status: focused PHP behavior growth for row-value `UPDATE`/`DELETE ... RETURNING` retry streams after savepoint rollback.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext237Plan`. It reuses native row-value UPDATE/DELETE RETURNING execution, rolls an attempted current-source stream back to the savepoint image, retries against that image, and materializes an `EXCLUDE CURRENT ROW`-style window receipt over the retry-yielded RETURNING rows.

The Application smoke models copied `wp_options` import rows where a rolled-back attempt mutates queued options and transient cleanup rows, then the retry returns paired multisite option rows. The window receipt verifies each blog partition sees peer rowids, peer names, and peer byte totals with the current row excluded.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext237Test.php`
- Result: `1 test files, 71 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next237.php --self-test`
- Result: `application-rowvalue-returning-window-current-source-next237 self-test passed`

Expected dashboard delta:

- `phpPass`: `117718 -> 117789` from 71 newly passing focused assertions.
- `phpFail`: remains `0`.
- `benchmarkDenominator.mapped`: unchanged at `640 / 1589`; this is focused current-source PHP behavior over the existing row-value/window surface rather than a new manifest-backed upstream inventory row.

Dependency closure: no new support component is needed. The slice reuses lane-local row-value UPDATE/DELETE RETURNING execution, savepoint row images, and bounded RETURNING window materialization.

Non-overlap: adds `EXCLUDE CURRENT ROW` peer-frame receipts over retry-yielded row-value RETURNING rows. It avoids accepted next233/next234 basic RETURNING window partitioning, next226 DISTINCT subqueries, next224 nested savepoint rollback, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, PRAGMA, and encoding clusters.

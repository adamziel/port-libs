# trigger-recursive-view-returning-current-source-next229

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext229Plan`, a current-source generation seal layered after the accepted next224 recursive view `INSTEAD OF` trigger `RETURNING` source seal.

The behavior keeps current-source `RETURNING` rows visible while attempted next-source rows remain held until the current source generation, view generation, trigger generation, and per-row generation seals are acknowledged. Missing, unexpected, stale source-generation, stale view-generation, stale trigger-generation, and ordered-seal mismatch cases fence next-source rows without hiding already-yielded current rows.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext229Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext229Test.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next229.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext229Test.php`
- Result: `1 test files, 105 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next229.php`
- Result: `wordpress-trigger-recursive-view-returning-current-source-next229 self-test passed`.

Expected dashboard movement: `phpPass +105` from the new focused test file. Mapped coverage remains unchanged because this is current-source PHP behavior over already mapped trigger/view/RETURNING inventory rather than a newly hydrated upstream row.

Dependency closure: no new support component is needed; this reuses the existing native recursive view/trigger `RETURNING` current-source, epoch, and next224 source-seal plans.

Non-overlap: this is after next224 source seals and avoids accepted next208 cursor close, next212 yield receipts, next218 epoch receipt admission, next224 source seals, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.

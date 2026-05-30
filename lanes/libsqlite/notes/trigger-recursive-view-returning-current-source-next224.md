# trigger-recursive-view-returning-current-source-next224

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext224Plan`, a current-source seal layered after the accepted next218 epoch receipts for recursive view `INSTEAD OF` trigger `RETURNING` rows.

The behavior keeps current-source `RETURNING` rows visible while next-source rows remain held until the current source token, view source, trigger source, and per-row source seals all match. Missing, unexpected, stale source-token, stale view-source, and stale trigger-source cases fence next-source rows without hiding the already-yielded current rows.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext224Test.php`
- Result: `1 test files, 94 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next224.php`
- Result: `application-trigger-recursive-view-returning-current-source-next224 self-test passed`.

Expected dashboard movement: `phpPass +94` from the new focused test file. Mapped coverage remains unchanged because this is current-source PHP behavior over already mapped trigger/view/RETURNING inventory rather than a newly hydrated upstream row.

Dependency closure: no new support component is needed; this reuses the existing native recursive view/trigger `RETURNING` current-source and epoch handoff plans.

Non-overlap: this is after next218 epoch receipts and avoids accepted next208 cursor close, next212 yield receipts, next218 epoch receipt admission, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.

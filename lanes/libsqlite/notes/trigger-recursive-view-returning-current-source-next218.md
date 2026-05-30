# trigger-recursive-view-returning-current-source-next218

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext218Plan`, a current-source epoch handoff layered after the accepted next212 current-source yield receipt fence.

The behavior models recursive view `INSTEAD OF` trigger `RETURNING` rows where the current source must publish a stable view/trigger epoch before the next source can become visible. Missing, unexpected, reversed, and mismatched epoch receipts keep next-source rows held while current-source rows remain visible.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext218Test.php`
- Result: `1 test files, 82 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next218.php`
- Result: `application-trigger-recursive-view-returning-current-source-next218 self-test passed`.

Dependency closure: no new support component is needed; this reuses the existing native recursive view/trigger RETURNING current-source plans and adds only an epoch-handoff fence on their produced rows.

Non-overlap: this is after next212 yield receipts and avoids accepted trigger recursive view RETURNING next157-next212 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.

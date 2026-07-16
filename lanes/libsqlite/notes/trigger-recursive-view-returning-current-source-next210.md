# trigger-recursive-view-returning-current-source-next210

Implemented an additive ordered current-source sequence fence after the
accepted next209 drain-watermark behavior. Current recursive view/trigger
RETURNING rows remain visible, but attempted next-source rows stay held until
the drained current-source rows acknowledge the exact ordered sequence,
handoff cursor, and source signature for the current view/trigger cookies.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext210Test.php`
- Result: `1 test files, 96 assertions, 0 failures` with 96 PASS lines.
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next210.php`
- Result: `application-trigger-recursive-view-returning-current-source-next210 self-test passed`.

Dependency closure: no new support component needed; this reuses native PHP
recursive view RETURNING current-source drain state and adds a bounded ordered
source-sequence admission fence.

Non-overlap: avoids accepted next209 drain watermarks, next208 cursor close,
next203 generation handoff, DML RETURNING conflicts, row-value RETURNING
savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree
clusters.

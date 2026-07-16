# trigger-recursive-view-returning-current-source-next208

Implemented an additive current-source RETURNING cursor close fence after the
accepted next206 yield watermark. Current recursive trigger/view RETURNING rows
remain visible, while attempted next-source rows are held until the current
RETURNING cursor close token and close watermark both match.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext208Test.php`
- Result: `1 test files, 74 assertions, 0 failures` with 74 PASS lines.
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next208.php`
- Result: `application-trigger-recursive-view-returning-current-source-next208 self-test passed`.

Dependency closure: no new support component needed; this reuses next206
current-source yield watermark state and adds a bounded cursor-close admission
fence.

Non-overlap: avoids accepted next206 yield watermark, next203 generation
handoff, DML RETURNING conflict, row-value RETURNING savepoint, schema reparse,
WAL/VFS, JSON table, planner, and B-tree clusters.

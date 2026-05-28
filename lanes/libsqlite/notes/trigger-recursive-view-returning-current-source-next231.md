# trigger-recursive-view-returning-current-source-next231

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext231Plan`.
It extends the accepted recursive view/trigger `RETURNING` current-source
handoff after `next222` by requiring the current-source RETURNING cursor close
receipts before the staged next-source rows are visible.

WordPress path: `wordpress-trigger-recursive-view-returning-current-source-next231.php`
models copied `wp_options` import rows routed through an INSTEAD OF view
trigger. The current-source recursive child rows remain visible while next
source rows are fenced until the current cursor close token and ordered close
receipts are acknowledged.

Non-overlap: this does not repeat next218 epoch or next222 source-ticket
admission. The new behavior is the post-ticket cursor-close lifecycle gate for
recursive view trigger RETURNING rows, avoiding row-value RETURNING savepoints,
DML conflict handling, schema reparse, WAL/VFS, JSON table, planner, encoding,
and B-tree clusters.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext231Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext231Test.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next231.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext231Test.php`
- Result: `1 test files, 89 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next231.php`
- Result: `wordpress-trigger-recursive-view-returning-current-source-next231 self-test passed`.

Dependency closure: no new support component is needed; this reuses the native
recursive view trigger RETURNING handoff chain, current-source source tickets,
row tagging, and focused TestRunner evidence.

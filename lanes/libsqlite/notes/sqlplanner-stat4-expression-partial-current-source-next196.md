# SQL planner STAT4 expression partial current-source next196

Status: focused PHP behavior growth for `sqlplanner-stat4-expression-partial-current-source-next196`.

Behavior: adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded current-source STAT4 partial expression-index planner fence for duplicate expression-key peers. After the accepted next192 covering-column fence admits the current source, next196 verifies that rows with the same `lower(option_name)` key stay in stable rowid order before a LIMIT/OFFSET window is reused.

Application path: `application-sqlplanner-stat4-expression-partial-current-source-next196.php` models copied `wp_options` plugin scans where mixed-case option names (`plugin_forms`, `Plugin_Forms`, `PLUGIN_FORMS`) collapse to the same expression-index key. The planner can avoid table lookup only when the current STAT4 partial-expression scan preserves deterministic peer rowid order.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext196Test.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next196.php --self-test`

Non-overlap: avoids accepted next192 covering-column admission, next191 payload expression-key checks, next189 payload partial fences, expression ORDER BY, expression-index range-cost, JSON, WAL, VFS, B-tree, trigger, and encoding clusters. The new surface is specifically duplicate expression-key peer ordering inside current-source STAT4 partial expression windows.

Dependency closure: no new support component needed; this composes existing native PHP current-source STAT4 expression partial fences and adds a lane-local peer-order admission check.

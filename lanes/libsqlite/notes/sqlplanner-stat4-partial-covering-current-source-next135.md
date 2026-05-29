# sqlplanner-stat4-partial-covering-current-source-next135

Adds `SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan`, an additive
planner wrapper that composes the existing STAT4 partial-covering current-source
comparator with current row-stream materialization. A stale prepared
`wp_options` partial covering index scan now reports the current schema/stat4
fence, covered current rowids, STAT4 anchor keys, current/next row stream, and
cursor opcodes that elide table lookup.

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-planner-stat4-partial-covering-current-source-next135.php --self-test`
- `wordpress-planner-stat4-partial-covering-current-source-next135 self-test passed`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialCoveringCurrentSourceNext135Test.php`
- `PASS ... 62 focused STAT4 partial covering current-source next135 cases`

Dependency closure: no new support component needed. This reuses the native
partial-index predicate, multicolumn range, STAT4 sample, and current-source
planner helpers already in the lane.

Non-overlap: avoids next131 ordinary partial range row streams, next124 partial
range deltas, next125/next127 skip-scan covering, next129 partial expression
skip-scan, next132 expression covering skip-scan, JSON planner/source/cursor,
VFS/WAL, B-tree, encoding, and suite-runner clusters. The new behavior is only
STAT4 partial-covering current-source row stream admission.

# SQLite planner STAT4 expression partial current-source next227

Status: focused PHP behavior growth for a STAT4 expression partial-index planner
fence over copied `wp_options` rows.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.
It composes the accepted next224 current-source sample-order fence, then admits
the prepared partial expression-index cursor only when each selected
`sqlite_stat4` sample's `neq` peer cardinality still matches the current
expression payload stream. Stale `neq` rows or stale payload peers force a
current-source reprepare instead of reusing a cursor that would split or skip
duplicate `lower(option_name)` peers.

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next227.php --self-test`
- Output: `wordpress-sqlplanner-stat4-expression-partial-current-source-next227 self-test passed`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext227Test.php`
- Result: `1 test files, 73 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses
existing lane-local STAT4 expression partial current-source planning, current
expression payloads, and cursor-program diagnostics.

Non-overlap: avoids accepted next224 sample-order validation, next219 peer-run
yield fences, grouped LIKE/OR admission, rowid alias, payload coverage,
expression ORDER BY, expression-index range-cost ranking, JSON, WAL, VFS,
B-tree, trigger, and UTF clusters. The new surface is `sqlite_stat4` `neq`
peer-cardinality validation against current partial expression-index payloads.

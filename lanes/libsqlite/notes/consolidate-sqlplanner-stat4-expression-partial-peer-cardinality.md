# SQLite planner STAT4 expression partial current-source peer-cardinality

Status: consolidation of the STAT4 expression partial-index peer-cardinality
planner fence over copied `wp_options` rows.

This slice keeps the behavior in the canonical
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` class while removing
the direct peer-cardinality worker-number test, example, note, and payload
names. The plan composes the accepted sample-order fence, then admits the
prepared partial expression-index cursor only when each selected
`sqlite_stat4` sample's `neq` peer cardinality still matches the current
expression payload stream. Stale `neq` rows or stale payload peers force a
current-source reprepare instead of reusing a cursor that would split or skip
duplicate `lower(option_name)` peers.

Application smoke:

- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-peer-cardinality.php --self-test`
- Output: `application-sqlplanner-stat4-expression-partial-peer-cardinality self-test passed`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPeerCardinalityTest.php`
- Result: `1 test files, 73 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses
existing lane-local STAT4 expression partial current-source planning, current
expression payloads, and cursor-program diagnostics.

Non-overlap: avoids accepted sample-order validation, peer-run yield fences,
grouped LIKE/OR admission, rowid alias, payload coverage, expression ORDER BY,
expression-index range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF
clusters. The retained behavior is `sqlite_stat4` `neq` peer-cardinality
validation against current partial expression-index payloads.

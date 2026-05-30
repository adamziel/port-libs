# sqlplanner-stat4-expression-partial-current-source-next156

Status: focused PHP behavior growth for a current-source SQL planner STAT4
partial expression-index edge.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.
It composes the existing expression-index planner with prepared/current source
fences so a stale prepared `wp_options` statement reparses to the current
partial expression index when schema cookie, STAT4 generation, root page, or
STAT4 sample signature changes. The selected index is intentionally
non-covering: STAT4 drives the partial expression cursor, while projected
`option_value` and `option_id` require a deferred table lookup.

Application path:
`application-stat4-expression-partial-current-source-next156.php` models plugin
option scans over `lower(option_name)` after a copied import refreshes STAT4
samples and adds mixed-case plugin rows.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext156Test.php`
  - `1 test files, 74 assertions, 0 failures`
  - `67` PASS lines
- `php lanes/libsqlite/examples/application-stat4-expression-partial-current-source-next156.php --self-test`
  - `application-stat4-expression-partial-current-source-next156 self-test passed`

Expected dashboard movement: `phpPass` +67, from `69549` to `69616`; mapped
coverage unchanged because this reuses existing expression-index, partial
predicate, current-source, and STAT4 manifest surfaces.

Non-overlap: avoids accepted STAT4 expression covering current-source,
partial collation STAT4, expression partial covering, expression ORDER BY,
range-cost, JSON, WAL/VFS, and B-tree clusters. The new surface is
non-covering partial expression STAT4 current-source selection with deferred
table lookup.

Dependency closure: no new support component is needed. The slice reuses
lane-local expression-index parsing, partial predicate proof, STAT4 estimates,
and bounded table row materialization.

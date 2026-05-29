# SQLite planner STAT4 expression partial current-source next229

Status: focused PHP behavior growth for `sqlplanner-stat4-expression-partial-current-source-next229`.

This slice adds a current-source selectivity fence after the accepted STAT4
partial expression sample-order fence. A prepared partial expression-index plan
is only reused when current `sqlite_stat4` counters still bracket the matched
row count, selected page window, and duplicate expression-key peer counts.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext229Test.php`
  - `1 test files, 78 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next229.php --self-test`
  - `wordpress-sqlplanner-stat4-expression-partial-current-source-next229 self-test passed`

WordPress smoke: copied `wp_options` plugin scans reuse a current-source
partial `lower(option_name)` expression index only when current STAT4
cardinality and peer-count counters still cover the selected page.

Dependency closure: no new support component is needed; this composes existing
current-source STAT4 expression partial fences and adds lane-local cardinality
proof metadata.

Non-overlap: avoids accepted next224 sample-order validation, next221 sample
windowing, next218 expression payload coverage, expression `ORDER BY`,
expression-index range-cost ranking, grouped SELECT, JSON table, WAL, VFS,
B-tree, trigger, and UTF clusters. The new surface is current STAT4
selectivity/cardinality proof for partial expression-index reuse.

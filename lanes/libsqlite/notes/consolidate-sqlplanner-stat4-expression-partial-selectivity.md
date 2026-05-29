# SQLite planner STAT4 expression partial current-source selectivity

Status: consolidation of `sqlplanner-stat4-expression-partial-selectivity`.

This slice keeps the current-source selectivity fence in the canonical STAT4
planner class while removing the direct selectivity worker-number test,
example, note, and payload names. A prepared partial expression-index plan is
only reused when current `sqlite_stat4` counters still bracket the matched row
count, selected page window, and duplicate expression-key peer counts.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialSelectivityTest.php`
  - `1 test files, 78 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-selectivity.php --self-test`
  - `wordpress-sqlplanner-stat4-expression-partial-selectivity self-test passed`

WordPress smoke: copied `wp_options` plugin scans reuse a current-source
partial `lower(option_name)` expression index only when current STAT4
cardinality and peer-count counters still cover the selected page.

Dependency closure: no new support component is needed; this composes existing
current-source STAT4 expression partial fences and adds lane-local cardinality
proof metadata.

Non-overlap: avoids accepted sample-order validation, sample windowing,
expression payload coverage, expression `ORDER BY`, expression-index
range-cost ranking, grouped SELECT, JSON table, WAL, VFS, B-tree, trigger, and
UTF clusters. The retained behavior is current STAT4 selectivity/cardinality
proof for partial expression-index reuse.

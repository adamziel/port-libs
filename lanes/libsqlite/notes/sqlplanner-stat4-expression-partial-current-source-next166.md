# sqlplanner-stat4-expression-partial-current-source-next166

Status: focused PHP behavior growth for STAT4 expression partial-index
current-source planning.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`,
a bounded planner composition for stale prepared `wp_options` statements that
must reprepare to the current source before using a partial expression index
for a multi-value `IN` constraint. It builds on the existing next154
current-source STAT4 row-stream helper, but keeps this behavior limited to
multi-key `IN` admission where the current partial predicate changed.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext166Test.php`
  - `1 test files, 71 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-current-source-next166.php --self-test`
  - `wordpress-planner-stat4-expression-partial-current-source-next166 self-test passed`

WordPress path:

The example models a copied `wp_options` plugin import where a prepared
partial expression index over `lower(option_name)` was built before a current
schema/stat4 refresh added `blog_id = 1` to the partial predicate. The planner
admits the current index only when all requested `IN` keys have exact STAT4
buckets, rejects stale next-source changes, and keeps network/lazy/null option
rows out of the row stream.

Non-overlap:

This avoids accepted STAT4 equality-bucket next162, equality+range next163,
covering row-stream, skip-scan, expression `ORDER BY`, expression-index
range-cost, JSON, WAL, VFS, and B-tree clusters. The new surface is
multi-key `IN` bucket admission with partial-predicate delta fencing.

Dependency closure:

No new support component is needed; this reuses lane-local STAT4 expression
partial planning, current-source fences, partial predicate implication, and
bounded row materialization.

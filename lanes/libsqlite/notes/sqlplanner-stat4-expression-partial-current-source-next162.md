# sqlplanner-stat4-expression-partial-current-source-next162

Status: focused PHP behavior growth for a current-source STAT4 expression
partial-index planner edge.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`,
a bounded composition over the existing next154 STAT4 expression partial helper.
It covers stale prepared statements where the current index keeps the same
expression equality key but changes the partial predicate, such as adding a
`blog_id = 1` term to a copied multisite `wp_options` partial index. The plan
records the partial-predicate delta, exact STAT4 equality buckets, blocked
prepared rowids, current-only rowids, next-source admission/replan reasons, and
a cursor program that fences the partial predicate before deferred table reads.

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next162.php --self-test`
- `wordpress-sqlplanner-stat4-expression-partial-current-source-next162 self-test passed`

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext162Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next162.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext162Test.php`
- Result: `1 test files, 68 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses
native STAT4 expression partial planning, current-source fences, partial
predicate implication, and bounded row diagnostics already present in the
libsqlite lane.

Non-overlap: avoids accepted next154 equality row-stream selection, next156
non-covering table lookup, next157 covering current-source admission, next158
range-window stale row exclusion, skip-scan, expression ORDER BY, range-cost,
JSON, WAL, VFS, and B-tree clusters. The new behavior is exact equality-bucket
admission only when the current partial predicate changed.

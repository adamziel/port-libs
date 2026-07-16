# SQL planner STAT4 expression partial current-source next178

Status: focused behavior growth for `sqlplanner-stat4-expression-partial-current-source-next178`.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded planner materializer for partial `lower(option_name)` expression indexes whose current STAT4 source can satisfy a descending expression `ORDER BY` without a temporary sort. It fences prepared statements on schema cookie, STAT4 generation, source signature, order signature, and row stream signature so stale prepared row order cannot leak after `ANALYZE` or copied `wp_options` source refresh.

Application path: copied `wp_options` plugin scans can use a current `lower(option_name) DESC` partial expression index for autoloaded plugin options after import/analyze churn, while rejecting rows outside the partial predicate and avoiding stale prepared ORDER BY output.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext178Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext178Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next178.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next178.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext178Test.php`
  - `1 test files, 71 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next178.php --self-test`
  - `application-sqlplanner-stat4-expression-partial-current-source-next178 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - no output

Dependency closure: no new support component is needed. The slice reuses lane-local expression normalization, partial predicate proof, STAT4 sample fences, current-source row diagnostics, and bounded cursor-program metadata.

Non-overlap: avoids accepted next164 range proof, next169 full expression-index cost, next173 duplicate STAT4 fanout, next175 LIKE prefix windows, expression ORDER BY text execution, expression-index range-cost ranking, JSON, WAL, VFS, and B-tree clusters. The new surface is current-source ORDER fence admission for a partial expression STAT4 scan.

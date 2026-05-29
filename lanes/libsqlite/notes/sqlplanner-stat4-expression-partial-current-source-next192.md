# SQL Planner STAT4 Expression Partial Current Source Next192

- Slice: `sqlplanner-stat4-expression-partial-current-source-next192`
- Behavior: current-source STAT4 partial expression-index scans now add a covering-column admission fence before eliding table lookups. The plan reuses next189 payload/predicate checks, then verifies that requested WordPress payload columns are present in the selected current index's covering payload or expression column.
- WordPress path: copied `wp_options` plugin admin scans over `lower(option_name)` can keep the current partial STAT4 plan only when `option_name`, `option_value`, `updated_at`, and `blog_id` remain covered.
- Non-overlap: avoids accepted next189 payload partial predicate checks, next188 peer fences, next186 IN windows, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and encoding clusters.
- Dependency closure: no new support component needed; the slice reuses the existing STAT4 expression partial current-source planner data model.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext192Test.php`
  - `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext192Test.php`
  - `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next192.php`
  - `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next192.php --self-test`
  - `git diff --check -- lanes/libsqlite`

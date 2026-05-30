# sqlplanner-stat4-expression-partial-current-source-next191

Status: focused behavior growth for STAT4 expression partial current-source planning.

Behavior:
- Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, layered on next188 peer rowid fencing.
- Rechecks each admitted current-source covering row by recomputing `lower(option_name)` from the current payload and comparing it with the indexed expression key.
- For STAT4 anchor rows, also verifies that the current `sqlite_stat4` sample key for that rowid still matches the current payload expression key.
- Blocks reuse when a stale STAT4 anchor or stale covering payload would let a partial `lower(option_name)` index return a row whose payload no longer matches the indexed expression key.

WordPress path:
- `wordpress-sqlplanner-stat4-expression-partial-payload-expression-fence.php` models copied `wp_options` plugin-option screens using a partial `lower(option_name)` STAT4 index after copied rows and ANALYZE data have changed.

Verification:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPayloadExpressionFenceTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPayloadExpressionFenceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-payload-expression-fence.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-payload-expression-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPayloadExpressionFenceTest.php`
  - `1 test files, 61 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-payload-expression-fence.php --self-test`
  - `wordpress-sqlplanner-stat4-expression-partial-payload-expression-fence self-test passed`

Non-overlap:
- Avoids accepted next188 duplicate peer rowid fencing, next185 sample provenance, next182 LIMIT/OFFSET covering windows, next180 descending scans, expression ORDER BY, expression-index range costs, JSON, WAL, VFS, B-tree, trigger, and UTF clusters.
- The new surface is the covering-payload expression-key recheck for current-source STAT4 partial expression indexes.

Dependency closure:
- No new support component is needed. The slice reuses current native PHP STAT4 expression partial planner materialization, current-source fences, covering payload rows, and WordPress smoke scaffolding.

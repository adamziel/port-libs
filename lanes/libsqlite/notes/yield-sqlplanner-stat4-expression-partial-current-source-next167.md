# sqlplanner-stat4-expression-partial-current-source-next167

## Behavior

- Added `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, composing the accepted next164 STAT4 partial expression-index planner with a post-ANALYZE sample-window fence.
- The slice proves a stale prepared `lower(option_name)` partial expression-index plan must block rowids that disappeared from the current STAT4 sample window while admitting current-only rowids from the refreshed `sqlite_stat4` samples.
- Application path: copied `wp_options` plugin-option scans after ANALYZE can continue using the current covering partial expression index while excluding stale prepared plugin rows and avoiding a table scan.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext167Test.php`
- Result: `1 test files, 63 assertions, 0 failures`
- PASS lines: `63`

## Application Smoke

- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next167.php --self-test`
- Result: `application-sqlplanner-stat4-expression-partial-current-source-next167 self-test passed`

## Non-Overlap

This avoids accepted next164 range-implies-partial proof, next158 stale-row range exclusion, next161 OR probes, expression ORDER BY, JSON table, WAL, VFS, and B-tree clusters. The new surface is only the post-ANALYZE STAT4 sample-window fence for current-source partial expression-index planning.

## Dependency Closure

No new support component is needed. The patch reuses lane-local native PHP current-source planning, STAT4 sample parsing, partial expression-index proof, and row-window diagnostics.

# sqlplanner-stat4-expression-partial-current-source-next169

## Behavior

- Added `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` for a current-source STAT4 planner handoff where a partial `lower(option_name)` expression index competes with a broader full expression index.
- The slice keeps the current partial expression index only when refreshed STAT4/source signatures make it cheaper than the full expression-index candidate, and records a cost fence with selected/full costs, candidate signatures, and rejected full-index candidates.
- It preserves stale prepared-statement reprepare behavior from the current source while proving a narrower planner decision: partial expression-index re-costing against a full expression-index fallback.
- WordPress path: copied `wp_options` plugin-option scans after ANALYZE/source changes can keep the partial plugin-option expression index instead of regressing to a broader full expression index over all options.

## Evidence

- Focused test:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext169Test.php`
  - Result: `1 test files, 62 assertions, 0 failures`
- Example smoke:
  - `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next169.php --self-test`
  - Result: `wordpress-sqlplanner-stat4-expression-partial-current-source-next169 self-test passed`

## Non-Overlap

This avoids accepted next154 equality/IN/BETWEEN row streams, next158 stale-row range windows, next161 OR-split probes, next164 range implication, next165 partial-range planning, expression ORDER BY, expression-index range-cost ranking, JSON table, WAL, VFS, and B-tree clusters. The new surface is current-source STAT4 re-costing between a partial expression index and a competing full expression index.

## Dependency Closure

No new support component is needed. The patch reuses lane-local native PHP expression normalization, current-source STAT4 range admission, and cost-fence diagnostics.

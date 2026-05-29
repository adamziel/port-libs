# sqlplanner-stat4-expression-partial-current-source-next164

## Behavior

- Added `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` for a bounded current-source planner handoff where a stale prepared statement has a partial `lower(option_name)` expression index and refreshed STAT4 range samples.
- The slice proves the partial expression-index predicate from narrower current query range bounds (`lower(option_name) >= plugin_cache` and `< plugin_t`) instead of requiring the query to repeat the original partial range verbatim.
- The planner selects the current source when schema/stat4/source signatures change, rejects stale prepared rows, keeps covering scans table-lookup-free, and falls back when the range is too wide to imply the partial predicate or when STAT4 samples are unavailable.
- WordPress path: copied `wp_options` plugin-option scans after ANALYZE/source changes can keep using the current partial expression index for autoloaded plugin options without reading stale prepared row payloads.

## Evidence

- Focused test:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext164Test.php`
  - Result: `1 test files, 55 assertions, 0 failures`
- Example smoke:
  - `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next164.php --self-test`
  - Result: `wordpress-sqlplanner-stat4-expression-partial-current-source-next164 self-test passed`

## Non-Overlap

This avoids accepted next154 equality/IN/BETWEEN row streams, next158 stale-row range exclusion, next161 OR-split probes, expression ORDER BY, JSON table, WAL, VFS, and B-tree clusters. The new surface is STAT4 partial expression-index admission when current query range bounds imply the partial range predicate after source/stat4 changes.

## Dependency Closure

No new support component is needed. The patch reuses lane-local native PHP expression normalization, partial-predicate proof, STAT4 sample fences, and current-source row diagnostics.

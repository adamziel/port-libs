# sqlplanner-stat4-expression-partial-current-source-next171

## Behavior

- Added `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` for current-source STAT4 partial expression indexes when an equality key is not itself a STAT4 sample.
- The slice admits a current `lower(option_name)` partial expression-index scan when the requested key is bracketed by neighboring current STAT4 samples and matching current rows exist.
- WordPress path: copied `wp_options` plugin scans can keep using the current partial expression index after `ANALYZE` even when `plugin_search` is not sampled directly.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext171Test.php`
- Result: `1 test files, 63 assertions, 0 failures`
- PASS lines: `63`

## WordPress Smoke

- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next171.php --self-test`
- Result: `wordpress-sqlplanner-stat4-expression-partial-current-source-next171 self-test passed`

## Non-Overlap

This avoids accepted next154 exact STAT4 equality/IN/BETWEEN row streams, next167 post-ANALYZE sample-window fences, next164 range proof, expression `ORDER BY`, range-cost ranking, JSON, WAL, VFS, and B-tree clusters. The new surface is unsampled equality-key admission through the current STAT4 bracket for a partial expression index.

## Dependency Closure

No new support component is needed. The patch reuses lane-local native PHP expression evaluation, partial predicate implication, STAT4 sample fences, and current-source row diagnostics.

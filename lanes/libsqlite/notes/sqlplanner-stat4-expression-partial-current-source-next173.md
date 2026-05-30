# sqlplanner-stat4-expression-partial-current-source-next173

## Behavior

- Added `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, composing the accepted next167 STAT4 partial expression current-source planner with duplicate-key STAT4 sample fanout.
- The slice models one `sqlite_stat4` sample key with `neq > 1`: a current partial `lower(option_name)` expression-index scan must expand all matching current rowids for that key, not only the sample rowid.
- Application path: copied `wp_options` plugin-option scans after `ANALYZE` can keep the current covering partial expression index when duplicate plugin option names differ only by case, while stale prepared rowids remain blocked.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext173Test.php`
- Result: `1 test files, 61 assertions, 0 failures`
- PASS lines: `61`

## Application Smoke

- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next173.php --self-test`
- Result: `application-sqlplanner-stat4-expression-partial-current-source-next173 self-test passed`

## Non-Overlap

This avoids accepted next167 post-ANALYZE sample-window drift, next164 range-implies-partial proof, next161 OR probes, expression `ORDER BY`, expression-index range-cost ranking, JSON table, WAL, VFS, and B-tree clusters. The new surface is only duplicate current rowid fanout behind one STAT4 sample key for a current partial expression index.

## Dependency Closure

No new support component is needed. The patch reuses lane-local native PHP current-source planning, STAT4 sample parsing, partial expression-index proof, and row-window diagnostics.

# JSON Table Generated Path Rowid Cost Current Source Next161

## Behavior

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext161()`.
- Extends accepted next158/next159 generated-path rowid cost profiles into an xBestIndex-style admission layer:
  - path and rowid constraints can be marked omitted when the current JSON source cursor remains pinned;
  - rowid `IN`/point/range seek metadata carries into admission cost classes;
  - next-source drift flips the planner source to reprepare and leaves constraints residual;
  - current/next transition reasons distinguish source, seek, rowset, usage, and cost changes.

## WordPress Scenario

`examples/wordpress-json-table-generated-path-rowid-cost-current-source-next161.php` models copied `wp_options` plugin rule diagnostics where a generated JSON path and rowid seek can reuse the current `json_tree()` cursor during a statement, while changed next-source JSON forces a fresh plan.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext161Test.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next161.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext161Test.php`
  - `1 test files, 59 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next161.php --self-test`
  - `wordpress-json-table-generated-path-rowid-cost-current-source-next161 self-test passed`

## Non-Overlap

This does not repeat accepted next158/next159 generated-path rowid cost or rowid seek-cost profiling. It adds the planner admission/constraint-usage layer above those profiles, leaving accepted JSON table cursor, SELECT/FROM source wiring, hidden/visible constraint pushdown, and generated path source profiles intact.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON table, JSON path, current-source, and rowid seek planners.

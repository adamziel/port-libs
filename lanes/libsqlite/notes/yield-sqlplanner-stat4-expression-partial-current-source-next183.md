# sqlplanner-stat4-expression-partial-current-source-next183

## Behavior

Adds current-source planner coverage for STAT4-backed partial expression indexes
when the constrained expression is an `IN (...)` list. The slice reuses the
accepted next171 single unsampled equality bracket machinery once per IN value,
then materializes a bounded multi-probe cursor shape with IN-list order,
rowid deduplication, and current-source reprepare fences.

Application path: copied `wp_options` plugin preload queries such as
`lower(option_name) IN ('plugin_shop','plugin_cache','plugin_mail')` can keep a
partial `lower(option_name)` STAT4 index after ANALYZE/schema churn without
falling back to a table scan or reusing stale prepared rowids.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext183Test.php`
  - `1 test files, 51 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next183.php --self-test`
  - `application-sqlplanner-stat4-expression-partial-current-source-next183 self-test passed`

Expected dashboard movement: `phpPass +51` from the new focused test file.
Mapped upstream coverage remains unchanged; this is focused current-source PHP
planner behavior over already mapped STAT4/expression/partial-index inventory,
not a newly hydrated upstream Tcl row.

## Non-Overlap

Avoids accepted next154 equality/IN/BETWEEN row streams, next168 LIKE-prefix
planning, next171 single unsampled equality brackets, next180 descending scan
direction, expression `ORDER BY`, range-cost ranking, JSON, WAL, VFS, B-tree,
and trigger clusters. This slice only covers IN-list multi-probe admission over
a current-source partial expression index.

## Dependency Closure

No new support component is needed. The patch reuses lane-local STAT4
expression partial planning and adds bounded native PHP multi-probe cursor
diagnostics.

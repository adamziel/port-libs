# compound-select-window-recursive-limit-current-source-next234

## Status

Adds a current-source compound SELECT slice for a multi-anchor recursive CTE where one anchor arm reads copied `wp_options` rows before recursive expansion. The plan fences current-source output before next-source autoload rows shift `row_number()` / `first_value()` window output, `UNION` / `INTERSECT` / `EXCEPT` membership, and the final compound `LIMIT/OFFSET` page.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext234Test.php`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next234.php`
- PHP lint: `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`, `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext234Test.php`, and `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next234.php`

## Non-Overlap

This does not repeat accepted next230 `avg()` / `first_value()` recursive-body-only queue work, next229 dense-rank `UNION` / `EXCEPT`, next228 row-number `INTERSECT` / `EXCEPT` drain behavior, parser-level JSON table source/cursor work, or accepted WAL/B-tree/VFS/encoding clusters.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `SQLiteSelectSql` compound execution, recursive CTE compound-anchor tracing, window output, current-source token fencing, and final compound LIMIT helpers.

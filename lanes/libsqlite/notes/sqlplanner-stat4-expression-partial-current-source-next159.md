# sqlplanner-stat4-expression-partial-current-source-next159

Status: focused PHP behavior growth for a STAT4 expression partial-index current-source yield planner.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded native PHP planner for stale prepared statements that can still use the current source of a non-skip-scan partial expression index. It records current-source rowids, STAT4 yield pairs, covering payload rows, table-lookup rows for non-covered columns, current/next source admission, and reprepare fences for schema-cookie, STAT4 generation, index signature, rowset, and cursor program changes.

WordPress path: `wordpress-planner-stat4-expression-partial-current-source-next159.php` models copied `wp_options` plugin option scans over `lower(option_name)` where the current source adds plugin rows and updated STAT4 samples while the prepared statement must yield from the stale source to the current source.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext159Test.php`
  - `1 test files, 69 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext159Test.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-current-source-next159.php`
  - `No syntax errors detected`
- `php lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-current-source-next159.php --self-test`
  - `wordpress-planner-stat4-expression-partial-current-source-next159 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed

PASS delta: `+69` focused PASS lines. `lane-status.json` `phpPass` moves from `70146` to `70215`. Mapped upstream coverage is unchanged because this reuses already mapped expression-index, partial-index, STAT4, and current-source planner inventory rather than adding a fresh manifest-backed row.

Non-overlap: avoids accepted expression ORDER BY, expression-index range-cost, STAT4 skip-scan, subquery partial-index, JSON, VFS, WAL, and B-tree clusters. The new surface is the non-skip-scan STAT4 expression partial current-source yield boundary with table-lookup fallback for non-covered requested columns.

Dependency closure: no new support component is needed. The patch reuses native partial-index proof, expression-key materialization, STAT4 sample diagnostics, and current-source fences.

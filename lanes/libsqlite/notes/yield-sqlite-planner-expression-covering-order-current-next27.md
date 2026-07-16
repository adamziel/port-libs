# yield-sqlite-planner-expression-covering-order-current-next27

## Status

Added bounded planner support for expression indexes whose trailing ordinary
columns make a `wp_options` lookup covering and satisfy `ORDER BY` after a
point expression constraint. This is intentionally separate from accepted SQL
expression `ORDER BY` execution and expression-index range-cost ranking: this
slice only exposes index-tail metadata to the planner and keeps range scans
from claiming trailing-column order.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerExpressionCoveringOrderCurrentNext27Test.php
Focused test run: 1 selected test files (root lock skipped)
26 PASS lines
1 test files, 49 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-select-expression-covering-order.php
```

The smoke reports `idx_wp_options_lower_autoload_order` selected with
`covering=true`, `orderBySatisfied=true`, and trailing columns
`autoload`, `option_id`, `option_value`.

## Non-Overlap

Avoids accepted parser-level SQL expression `ORDER BY`, SELECT SQL text/JOIN/
GROUP BY/subquery execution, expression-index range-cost ranking, JSON table
source/cursor/constraint work, VFS writer/lock/sync/rollback paths, WAL byte
truncation/checkpoint transactions, B-tree page move/root collapse/interior
merge/overflow freelist release, and Unicode GLOB ranges.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
`SQLiteCreateIndex`, `SQLiteIndexColumn`, and `SQLiteSelectExpressionIndexPlan`
metadata.

## Next

Wire this metadata into broader planner/executor cost selection once the native
SELECT executor chooses between table scans and index scans from schema records.

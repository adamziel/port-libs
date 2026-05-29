# JSON Table Generated Path Cost Current Source Next134

## Slice

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathCost()` for current/next-source JSON table planning when a copied WordPress host row carries a generated JSON path column.
- The planner layers on accepted path ORDER BY cost behavior without repeating JSON table cursor, SELECT-source, hidden constraint, visible constraint, path ORDER BY, or path/rowid cost clusters.
- It validates the generated path column, compares it with the selected `path` constraint (`=`, `LIKE`, and `IN`), records coverage/cost transitions, and preserves current-source replan reasons.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathCostTest.php`
- Result: `1 test files, 61 assertions, 0 failures`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-json-table-generated-path-cost.php`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses existing native PHP JSON parser/tree/path/planner components under `lanes/libsqlite/src`.

## Non-Overlap

This avoids accepted/current-source surfaces for JSON table cursor behavior, parser-level `json_each`/`json_tree` SELECT sources, hidden/visible constraint extraction, path ORDER BY cost next131, path hidden rowid cost next126, indexed constraint cost next119, and batch131 JSON table path ORDER BY cost planning.

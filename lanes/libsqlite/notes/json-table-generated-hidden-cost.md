# JSON table generated hidden cost current-source next136

## Behavior

Adds `SQLiteJsonTablePlan::currentSourceGeneratedHiddenCost()`, layering generated-column predicates over the existing nested hidden JSON table cost path. The planner re-costs `json_tree()` hidden-source scans when generated values from each row, such as `$.priority` and `$.enabled`, change the filtered rowid set between current and next copied `wp_options` source rows.

This avoids accepted JSON table source/cursor/visible-constraint surfaces. It reuses the next129 nested hidden-cost planner and adds only generated-value filtering, rowid/fullkey tapes, cost-class transitions, and current-source reader policies.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedHiddenCostTest.php`
  - `1 test files, 64 assertions, 0 failures`
  - 64 PASS lines
- `php lanes/libsqlite/examples/application-json-table-generated-hidden-cost.php`
  - Application smoke passed with current rowids `[9]` and next rowids `[9, 13]`.

## Non-overlap

Does not repeat accepted JSON hidden constraints, visible constraint pushdown, JSON table cursor behavior, parser-level JSON table SELECT sources, nested hidden cost next129, nested path rowid next133, or hidden generated order next132. This slice is the generated-predicate cost layer over the existing hidden nested source.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON path extraction, JSON table row production, nested hidden-cost planning, and current-source comparison utilities.

# JSON Table Nested Constraint Cost Current Source Next125

## Slice

- Adds `SQLiteJsonTablePlan::currentSourceNestedConstraintCost()` for current-source `json_tree()` planning where a host row stores both a base JSON root and a nested path fragment.
- The planner composes the current/next nested roots, reuses the existing xBestIndex/cost-order/indexed-constraint profiles, and records next125 transitions for nested root, path mode, matched row count, matched fullkeys, selected indexed constraint, and effective cost.
- Adds a WordPress smoke for copied `wp_options` plugin rule settings whose request-level nested path moves from one rule group to another.

## Evidence

- Focused test command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableNestedConstraintCostTest.php`
- Example smoke: `php lanes/libsqlite/examples/wordpress-json-table-nested-constraint-cost.php`
- PHP lint: `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`, `php -l lanes/libsqlite/tests/SQLiteJsonTableNestedConstraintCostTest.php`, `php -l lanes/libsqlite/examples/wordpress-json-table-nested-constraint-cost.php`
- Diff check: `git diff --check -- lanes/libsqlite`

## Non-Overlap

- Does not repeat accepted JSON table hidden constraints, visible constraints, parser-level JSON table SELECT sources, JSON cursor behavior, indexed hidden order next122, or nested path composition next121.
- This slice is narrower: it covers the cost/selectivity handoff when the nested path changes which visible fullkey/id constraints are costed and which rows/fullkeys are matched.

## Dependency Closure

- No new support component is needed. The slice reuses existing JSON path parsing, JSON tree row production, visible constraint filtering, and current-source planner profiles.

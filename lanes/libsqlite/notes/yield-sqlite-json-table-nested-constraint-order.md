# yield-sqlite-json-table-nested-constraint-order-current-source-next127

Implemented `SQLiteJsonTablePlan::currentSourceNestedConstraintOrder()`.

Behavior:
- Composes current and next nested JSON table roots from a base root column plus a nested path fragment.
- Delegates the composed root into the accepted current-source partial ORDER BY cost planner.
- Preserves constant visible constraint coverage, consumed ORDER BY prefixes, suffix block-sort costs, row order, and current/next replan reasons.
- Adds a Application smoke for copied `wp_options` plugin settings where `json_tree()` scans nested rule arrays and orders priority leaves by `key, atom DESC`.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableNestedConstraintOrderTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 64 assertions, 0 failures
```

Non-overlap:
This avoids accepted parser-level JSON table SELECT source/cursor behavior, hidden/visible constraint extraction, path constraint pushdown, nested path planning next121, ORDER BY constraint coverage next120, partial ORDER BY cost next124, and next113 constraint/cost/order handling. The new surface is their current-source composition: nested root path changes plus constant visible constraint ORDER-prefix consumption and suffix-sort cost in one planner state.

Dependency closure:
No new support component is needed. The slice reuses native PHP JSON path composition, JSON table row materialization, visible constraint pushdown, current-source planning, and partial ORDER BY cost profiling.

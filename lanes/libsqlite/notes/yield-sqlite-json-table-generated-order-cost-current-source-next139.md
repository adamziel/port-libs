# yield-sqlite-json-table-generated-order-cost-current-source-next139

Status: focused PHP behavior growth for JSON table generated-column filtering plus generated ORDER BY cost at the current/next source boundary.

Behavior:

- Added `SQLiteJsonTablePlan::currentSourceGeneratedOrderCostNext139()`.
- Reuses accepted nested hidden-source cost and generated hidden constraint filtering, then orders only the filtered `json_tree()` rows by generated keys extracted from each row.
- Reports ordered rowids/fullkeys, generated order keys, sorter requirement, sort penalty, effective cost, cost class, transitions, and next139 replan reasons.
- Preserves the current reader until cursor reset while preparing a changed next generated-order-cost plan.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedOrderCostCurrentSourceNext139Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/wordpress-json-table-generated-order-cost-current-source-next139.php
currentOrderedRowids: [11]
nextOrderedRowids: [16, 11]
nextReaderPolicy: prepare-next-json-table-generated-order-cost-plan
```

Non-overlap: avoids accepted JSON table cursor/source wiring, hidden/visible constraint extraction, path ORDER BY cost next131, hidden generated order next132, generated hidden cost next136, hidden rowid ORDER, generated-index operators, JSON aggregate ORDER BY, SQL expression ORDER BY, VFS/WAL/B-tree clusters, and Unicode GLOB work. This slice combines generated filtering with generated ORDER BY cost over the already-planned nested hidden JSON table source.

Dependency closure: no new support component is needed. The slice reuses native JSON table planning, JSON path validation/extraction, generated hidden constraint filtering, and row-array ordering.

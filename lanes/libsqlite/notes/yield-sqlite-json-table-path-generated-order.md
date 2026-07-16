# JSON table path generated ORDER current-source next137

Behavior slice: adds `SQLiteJsonTablePlan::currentSourcePathGeneratedOrder()` for the current-source planner boundary where a path-constrained `json_tree()` cursor is ordered by generated JSON keys extracted from each filtered row.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTablePathGeneratedOrderTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 56 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-path-generated-order.php --self-test
application-json-table-path-generated-order self-test passed
```

Non-overlap: this composes accepted JSON table path pushdown/order-cost behavior (`next131`) with generated per-row ordering, without repeating hidden generated ordering (`next132`), nested path rowid handling (`next133`), visible/hidden constraint extraction, JSON table SELECT source/cursor wiring, or host/dynamic join materialization.

Dependency closure: no new support component is needed; this reuses native JSON table path pushdown, current-source row materialization, and generated JSON-key ordering helpers.

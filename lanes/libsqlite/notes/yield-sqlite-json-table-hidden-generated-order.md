# yield-sqlite-json-table-hidden-generated-order-current-source-next132

Behavior slice: adds `SQLiteJsonTablePlan::currentSourceHiddenGeneratedOrder()` for the current-source planner boundary where a `json_tree()` cursor is ordered by hidden `json`/`root` terms and by generated ORDER BY keys extracted from each JSON table row.

The planner now records normalized generated order terms, generated keys, ordered rowid/fullkey tapes, generated-sort cost, current/next transitions, and replan reasons when a copied `wp_options` JSON source changes priorities while the current hidden-source cursor remains pinned.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenGeneratedOrderTest.php
# 1 test files, 57 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-json-table-hidden-generated-order.php
# emits current ordered rowids [1,9,5] and next ordered rowids [5,9,13,1]
```

Non-overlap: avoids accepted JSON table cursor/source/hidden/visible constraint pushdown, next113/119/122/123/126/127/129 cost/order layers as standalone behavior, JSON generated-index operators, JSON aggregate ORDER BY, SQL expression ORDER BY, VFS/WAL/B-tree clusters, and Unicode GLOB work. This patch only adds the generated-key ORDER BY layer on top of an existing hidden json/root current-source plan.

Dependency closure: no new support component is needed. The slice reuses native JSON table planning, JSON path validation/extraction, hidden order metadata, and row-array ordering.

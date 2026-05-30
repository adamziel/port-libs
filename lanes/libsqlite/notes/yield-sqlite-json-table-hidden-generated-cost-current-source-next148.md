# JSON table hidden generated cost current-source next148

Behavior slice: adds `SQLiteJsonTablePlan::currentSourceHiddenGeneratedCostNext148()` for the planner boundary where hidden `json_tree()` path/rowid constraints and generated JSON value filters are costed together as xBestIndex-style argv/omit metadata.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenGeneratedCostCurrentSourceNext148Test.php
```

Result: `1 test files, 63 assertions, 0 failures`.

Application smoke:

```sh
php lanes/libsqlite/examples/application-json-table-hidden-generated-cost-current-source-next148.php --self-test
```

Result: `application-json-table-hidden-generated-cost-current-source-next148 self-test passed`.

Non-overlap: avoids accepted JSON hidden/visible constraint extraction, JSON table SELECT source/cursor wiring, generated hidden rowid cost next142, hidden path generated current-source value filtering next143, generated hidden path current-source next144, and batch143 JSON table generated hidden path costing. This slice adds the distinct cost/argv/omit planner profile needed after those rows already exist.

Dependency closure: no new support component is needed; this reuses native JSON table hidden path/rowid source seeks and generated JSON value extraction.

# consolidate-final-numbered-methods-json-table-forty-eighth-pass

Status: consolidated the JSON table generated ORDER BY cost current-source production surface into stable descriptive method/helper names.

Behavior:

- Renamed `SQLiteJsonTablePlan::the prior numbered public method` to `SQLiteJsonTablePlan::currentSourceGeneratedOrderCostPlan()`.
- Renamed the matching private generated-order cost helper methods by removing the numeric suffix.
- Migrated the direct focused test and Application example to stable filenames and stable dependency/replan keys.
- Reuses accepted nested hidden-source cost and generated hidden constraint filtering, then orders only the filtered `json_tree()` rows by generated keys extracted from each row.
- Reports ordered rowids/fullkeys, generated order keys, sorter requirement, sort penalty, effective cost, cost class, transitions, and `generatedOrderCostReplanReasons`.
- Preserves the current reader until cursor reset while preparing a changed next generated-order-cost plan.

Verification:

```text
$ php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php

$ php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedOrderCostCurrentSourcePlanTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableGeneratedOrderCostCurrentSourcePlanTest.php

$ php -l lanes/libsqlite/examples/application-json-table-generated-order-cost-current-source-plan.php
No syntax errors detected in lanes/libsqlite/examples/application-json-table-generated-order-cost-current-source-plan.php

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedOrderCostCurrentSourcePlanTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-json-table-generated-order-cost-current-source-plan.php
operation: json-table-generated-order-cost-current-source-plan
currentOrderedRowids: [11]
nextOrderedRowids: [16,11]
```

Non-overlap: this is a consolidation-only pass over the existing JSON table generated ORDER BY cost surface. It does not change JSON table cursor/source wiring, hidden/visible constraint extraction, path ORDER BY cost, hidden generated order, generated hidden cost, hidden rowid ORDER, generated-index operators, JSON aggregate ORDER BY, SQL expression ORDER BY, VFS/WAL/B-tree clusters, or Unicode GLOB work.

Dependency closure: no new support component is needed. The slice reuses native JSON table planning, JSON path validation/extraction, generated hidden constraint filtering, and row-array ordering.

# JSON table generated path rowid cost current-source next173

Behavior slice: adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext173()` for xBestIndex-style planner evidence where generated JSON path constraints and rowid argv constraints are both usable on a pinned current-source `json_tree()` cursor.

Focused evidence:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext173Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 62 assertions, 0 failures
```

Application smoke:

```bash
php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next173.php --self-test
```

Result:

```text
application-json-table-generated-path-rowid-cost-current-source-next173 self-test passed
```

Non-overlap: this does not repeat accepted JSON table cursor/source wiring, hidden/visible constraint extraction, generated path rowid xFilter next167, generated path/order next137, hidden rowid path next146, or accepted next167 batch159 behavior. It adds the narrower best-index admission/cost/fingerprint layer for generated path plus rowid current-source constraints.

Dependency closure: no new support component is needed; this reuses native JSON table current-source, generated-path rowid-cost, xFilter, and xBestIndex planner metadata helpers.

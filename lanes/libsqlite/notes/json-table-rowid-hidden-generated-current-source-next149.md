# JSON table rowid hidden generated current-source next149

Behavior slice: adds `SQLiteJsonTablePlan::currentSourceRowidHiddenGeneratedNext149()` for the current-source planner boundary where a rowid-constrained `json_tree()` cursor also exposes generated hidden output columns. The plan reuses accepted generated hidden rowid costing, then records matched rowids/fullkeys, generated output values, stable fingerprints, value-drift transitions, and current/next reader policies.

Application path: `application-json-table-rowid-hidden-generated-current-source-next149.php` models copied `wp_options` plugin settings where a prepared import preview pins a JSON table row by hidden rowid while generated slug/priority/enabled columns can change across the next source.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenGeneratedCurrentSourceNext149Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 55 assertions, 0 failures
```

Expected dashboard delta: `phpPass` +55, from `66428` to `66483`; mapped upstream coverage is unchanged because this is focused current-source PHP behavior over existing JSON table inventory.

Non-overlap: avoids accepted JSON table hidden rowid constraints, generated hidden cost, generated hidden rowid cost next142, generated path rowid cost next145, visible/hidden constraint extraction, JSON table SELECT source/cursor behavior, generated path ordering, and batch144 JSON generated path rowid costing. This slice only adds the missing generated output/value-drift layer after the rowid-hidden generated cursor is already selected.

Dependency closure: no new support component is needed; this reuses native JSON table row materialization, hidden rowid alias matching, generated hidden value extraction, JSONB input handling, and current/next source transition helpers.

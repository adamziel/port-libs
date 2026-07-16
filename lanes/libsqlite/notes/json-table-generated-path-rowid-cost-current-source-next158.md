# json-table-generated-path-rowid-cost-current-source-next158

Behavior slice: adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostNext158()` for the current-source planner boundary after generated path + rowid costing has selected a bounded `json_tree()` cursor. This layer records source fingerprints, generated-path fingerprints, rowid intersection stability, pin keys, reuse decisions, pinned estimated costs, and next158 replan reasons.

Application path: `application-json-table-generated-path-rowid-cost-current-source-next158.php --self-test` models copied `wp_options` plugin settings where the current source can keep a generated-path rowid seek pinned, while the next import shifts sibling rules and must prepare a fresh cursor.

Non-overlap: avoids accepted JSON table visible/hidden constraint extraction, SELECT source/cursor behavior, generated hidden rowid cost next142, generated path rowid cost next145, rowid-hidden generated output next149, and batch148 JSON rowid/hidden/generated behavior. This slice only adds the current-source reuse/pin admission layer above existing generated-path rowid costing.

Dependency closure: no new support component needed; reuses native JSON table planning, JSON path validation, JSON input validation, and current-source fingerprinting.

Focused verification:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext158Test.php
# 1 test files, 57 assertions, 0 failures

php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next158.php --self-test
# application-json-table-generated-path-rowid-cost-current-source-next158 self-test passed
```

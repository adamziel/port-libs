# JSON table generated path rowid cost current-source next188

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next188`

What changed:

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext188()`.
- Extends accepted next185 resume checkpoints with a deleted-rowid guard for generated-path rowid cursors.
- Records current candidate rowids, next-source rowids, deleted/retained/inserted rowids, source fingerprints, restart policy, estimated cost, and next188 replan reasons.
- Prevents copied `wp_options` JSON diagnostics from resuming a current `json_tree()` checkpoint when the next generated path removes rowids from the checkpoint tape.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext188Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext188Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next188.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next188.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext188Test.php`
  - `1 test files, 47 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next188.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next188 self-test passed`

Non-overlap:

This avoids accepted JSON table cursor/source wiring, hidden/visible constraints, SELECT JSON table sources, next185 resume checkpoint materialization, generated-path xNext admission through next185, and VFS/WAL/B-tree/SQL planner surfaces. The new behavior is specifically the deleted/generated-path rowid restart guard above the accepted resume checkpoint.

Dependency closure:

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path rowid resume checkpoints, JSON path validation, and current-source fingerprints.

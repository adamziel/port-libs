# JSON table generated path rowid cost current-source next191

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next191`

## Delta

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext191()`.
- Builds on accepted next185 resume checkpoints and next188 deleted-rowid restart detection.
- Adds an xFilter-style current-source recheck for delivered checkpoint rowids before a generated-path `json_tree()`/`json_each()` cursor is reused.
- Records xFilter argv, accepted/rejected checkpoint rowids, path/value match tape, stale next-source state, replan reasons, and point/range/restart cost classes.
- Prevents copied `wp_options` JSON diagnostics from reusing a generated-path rowid checkpoint when the next source keeps a rowid tape but no longer has the same row under the generated path.

## Focused evidence

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext191Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext191Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext191Test.php`
  - `1 test files, 62 assertions, 0 failures`

Application smoke:

- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next191.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next191 self-test passed`

## Non-overlap

This avoids accepted JSON table cursor/source wiring, hidden and visible constraints, generated-path rowid cost/cache/yield/xNext/deleted-resume layers through next188, and storage/VFS/B-tree surfaces. The new behavior is the xFilter recheck above the delivered current-source checkpoint: rowids are reusable only when their current source row still exists, still matches the generated path, and still matches the projected scalar value.

## Dependency closure

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path validation, rowid resume checkpoints, and current-source fingerprints.

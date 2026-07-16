# JSON table generated path rowid cost current-source next228-232

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next228-232`

## Implementation

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext232()`.
- Builds on accepted generated-path rowid current-source guards through next227.
- Adds a current-source batch token over source fingerprint, yielded rowids, restart rowids, opcode, and batch size so copied `wp_options` JSON diagnostics do not admit next-source rows through a stale generated-path `json_tree()` rowid batch.
- Records dependencies for next228, next229, next230, next231, and next232 as one focused current-source coverage slice.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext232Test.php`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next232.php --self-test`
- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext232Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next232.php`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This avoids parser JSON table sources, hidden constraint extraction, JSONB, aggregate JSON, WAL/VFS/B-tree, and prior generated-path rowid source/yield guards through next227. The new surface is only the batch-token admission boundary after the current-source fingerprint guard.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path rowid planning, row projection, current-source fingerprints, and JSON path validation.

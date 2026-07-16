# JSON table generated path rowid cost current-source next195

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next195`

## Implementation

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext195()`.
- Extends the accepted generated-path rowid xFilter checkpoint with fullkey anchor validation.
- Detects when a copied `wp_options` JSON source keeps the same generated-path row but shifts the `json_tree` rowid after object layout changes.
- Reports stable rowid anchors, fullkey-remapped rowids, collisions, missing anchors, resume mode, cost class, and replan reasons.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext195Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext195Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-anchor-next195.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-json-table-generated-path-rowid-anchor-next195.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext195Test.php`
  - `1 test files, 57 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-anchor-next195.php --self-test`
  - `application-json-table-generated-path-rowid-anchor-next195 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - no output

## Non-Overlap

This avoids accepted JSON table cursor/source wiring, hidden and visible constraints, generated-path rowid resume/xFilter layers through next191, and storage/VFS/B-tree surfaces. The new behavior is specifically the fullkey anchor guard above rowid xFilter checkpoints, preventing stale rowid-only resume when JSON object layout shifts `json_tree` ids.

## Dependency Closure

No new support component is needed. The slice reuses native JSON table row generation, generated-path rowid xFilter checkpoints, JSON path validation, and fullkey anchors.

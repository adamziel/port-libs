# JSON table generated path rowid cost current-source next206

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next206`

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext206()`.
- Extends accepted generated-path rowid alias xColumn projection with a planner layer for `ORDER BY rowid`, `_rowid_`, `oid`, and `id` consumption.
- Records normalized alias order terms, unsupported order columns, ordered rowid tape, `orderByConsumed`, alias-order reuse, opcode, cost class, fingerprint, and current/next replan reasons.
- Prevents copied `wp_options` JSON diagnostics from reusing a rowid-alias ordered cursor after next-source projection/cache invalidation.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext206Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext206Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next206.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next206.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext206Test.php`
  - `1 test files, 50 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next206.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next206 self-test passed`

Expected dashboard movement: `phpPass +50` from the focused TestRunner PASS lines. No mapped upstream denominator change is claimed.

Non-overlap: avoids accepted JSON table cursor/source wiring, hidden/visible constraints, parser-level JSON table SELECT sources, generated-path rowid cost/cache/yield/xNext/xColumn layers through next203, accepted SQL expression `ORDER BY`, and storage/VFS/B-tree surfaces. The new behavior is specifically JSON virtual-table rowid-alias `ORDER BY` consumption above generated-path alias projection.

Dependency closure: no new support component is needed. This reuses native PHP JSON table row generation, generated-path rowid xColumn cache materialization, rowid alias projection, JSON path validation, and planner metadata helpers.

Next task: wire this alias-order consumption into broader parser-level `json_each()` / `json_tree()` SELECT planning if the executor starts producing native vtab `orderByConsumed` decisions directly from SQL text.

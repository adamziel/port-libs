# JSON table generated path rowid cost current-source next208

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next208`

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext208()`.
- Extends accepted generated-path rowid alias `ORDER BY` consumption with a final current-source cost profile after rowid tape ordering and LIMIT admission.
- Records final rowids, first/last final rowid, bounded estimated rows/cost, final-cost opcode, cost class, fingerprint, and current/next replan reasons.
- Prevents copied `wp_options` JSON diagnostics from reusing a final-cost rowid tape when the next imported option changes generated path, source generation, or rowid admission.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext208Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext208Test.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next208.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next208.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext208Test.php`
  - `1 test files, 48 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next208.php --self-test`
  - `wordpress-json-table-generated-path-rowid-cost-current-source-next208 self-test passed`

Expected dashboard movement: `phpPass +48` from the focused TestRunner PASS lines. No mapped upstream denominator change is claimed.

Non-overlap: avoids accepted JSON table cursor/source wiring, hidden/visible constraints, parser-level JSON table SELECT sources, generated-path rowid cost/cache/yield/xNext/xColumn/alias-order layers through next206, accepted SQL expression `ORDER BY`, and storage/VFS/B-tree surfaces. The new behavior is specifically JSON virtual-table generated-path rowid final-cost admission above accepted alias-order consumption.

Dependency closure: no new support component is needed. This reuses native PHP JSON table row generation, generated-path rowid xColumn cache materialization, rowid alias projection/order planning, JSON path validation, and planner metadata helpers.

Next task: wire final-cost admission into broader parser-level `json_each()` / `json_tree()` planning when the SELECT executor starts propagating native virtual-table estimated cost decisions directly from SQL text.

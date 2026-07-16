# JSON table generated path rowid cost current-source next207

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next207`

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext207()`.
- Extends next206 rowid-alias `ORDER BY` consumption with a bounded `LIMIT` / `OFFSET` layer over the current-source generated-path rowid cursor.
- Records skipped rowids, bounded rowids, remaining rowids, limit tape, `limitConsumed`, `limitReusable`, opcode, cost class, fingerprint, and current/next replan reasons.
- Prevents copied `wp_options` JSON diagnostics from reusing a bounded rowid-alias cursor after next-source changes invalidate the current source.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext207Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext207Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next207.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next207.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext207Test.php`
  - `1 test files, 61 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next207.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next207 self-test passed`

Expected dashboard movement: `phpPass +61` from the focused TestRunner PASS lines. No mapped upstream denominator change is claimed.

Non-overlap: avoids accepted JSON table cursor/source wiring, hidden/visible constraints, parser-level JSON table SELECT sources, generated-path rowid cost/cache/yield/xNext/xColumn layers through next206, SQL expression `ORDER BY`, and storage/VFS/B-tree surfaces. The new behavior is specifically JSON virtual-table rowid-alias `ORDER BY` plus `LIMIT` / `OFFSET` consumption above generated-path alias ordering.

Dependency closure: no new support component is needed. This reuses native PHP JSON table row generation, generated-path rowid xColumn cache materialization, rowid alias projection/order metadata, JSON path validation, and planner metadata helpers.

Next task: wire this bounded alias-order limit consumption into broader parser-level `json_each()` / `json_tree()` SELECT planning when the executor starts producing native vtab `orderByConsumed` and LIMIT/OFFSET decisions directly from SQL text.

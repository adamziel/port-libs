# JSON table generated path rowid cost current-source next209

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next209`

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext209()`.
- Extends the accepted generated-path rowid alias order layer with rowid-alias range constraint costing for `BETWEEN`, `>`, `>=`, `<`, and `<=`.
- Records normalized range constraints, ordered rowids before the range gate, accepted/rejected range rowids, range selectivity, opcode, cost class, fingerprint, and current/next replan reasons.
- Prevents copied `wp_options` JSON diagnostics from reusing a generated-path rowid range cursor after next-source generation/path changes.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext209Test.php`
  - `1 test files, 50 assertions, 0 failures`

Expected dashboard movement: `phpPass +50` from the focused TestRunner PASS lines. No mapped upstream denominator change is claimed.

Non-overlap: avoids accepted JSON table cursor/source wiring, hidden/visible constraints, parser-level JSON table SELECT sources, generated-path rowid cost/cache/yield/xNext/xColumn/alias-order layers through next206, SQL expression `ORDER BY`, B-tree, WAL, pager, VFS, and encoding surfaces. The new behavior is specifically JSON virtual-table generated-path rowid-alias range constraint costing above accepted rowid alias ordering.

Dependency closure: no new support component is needed. This reuses native PHP JSON table row generation, generated-path rowid xColumn cache materialization, rowid alias projection/order metadata, JSON path validation, and planner metadata helpers.

Next task: wire the range-cost profile into parser-level `json_each()` / `json_tree()` SELECT planning when native virtual-table `xBestIndex` constraint arrays are produced directly from SQL text.

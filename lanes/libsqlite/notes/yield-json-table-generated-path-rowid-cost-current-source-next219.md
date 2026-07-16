# JSON table generated path rowid cost current-source next219

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next219`

## Implementation

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext219()`.
- Extends the accepted generated-path rowid xCurrent layer with ORDER BY rowid plus LIMIT admission metadata.
- Reports bounded rowid tape, active rowid ordinal, active-within-limit admission, reusable limit opcode, estimated rows/cost, limit fingerprint, current/next transitions, and next-source replan reasons.
- Prevents copied `wp_options` JSON diagnostics from reusing an active generated-path rowid cursor when the next source changes or the active row falls outside the consumed ORDER/LIMIT boundary.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext219Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext219Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next219.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next219.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext219Test.php`
  - `1 test files, 52 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next219.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next219 self-test passed`

## Non-Overlap

This avoids accepted JSON table cursor/source wiring, hidden/visible constraints, generated-path rowid cost/cache/yield/xNext/range/xCurrent layers through next212, JSON aggregate/window behavior, and storage/VFS/B-tree surfaces. The new behavior is specifically the bounded ORDER BY rowid plus LIMIT admission check over the current active xCurrent row and next-source reprepare boundary.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path path validation, rowid alias ORDER BY metadata, xCurrent materialization, and JSON path planning.

# JSON table generated path rowid cost current-source next212

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next212`

## Implementation

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext212()`.
- Builds on accepted generated-path rowid range constraints through next209.
- Pins the current xCurrent row for generated-path `json_tree()` rowid ranges, including active rowid, remaining rowids, projected columns, rowid aliases, source/range/projection fingerprints, opcode, cost class, and stale next-source reprepare state.
- Prevents copied `wp_options` JSON diagnostics from reusing a current-source xCurrent row after generated path or source fingerprints change.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext212Test.php`
  - `1 test files, 59 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next212.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next212 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext212Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext212Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next212.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next212.php`
- `git diff --check -- lanes/libsqlite`
  - no output

## Non-Overlap

This avoids accepted JSON table cursor/source wiring, hidden/visible constraint extraction, parser-level JSON table SELECT sources, generated-path rowid resume/xfilter/xnext/range layers through next209, and VFS/WAL/B-tree surfaces. The new behavior is the xCurrent pinning boundary above the generated-path rowid range plan.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path rowid range planning, rowid alias projection, current-source fingerprints, and JSON path validation.

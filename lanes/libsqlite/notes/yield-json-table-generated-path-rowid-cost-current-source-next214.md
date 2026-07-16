# JSON table generated path rowid cost current-source next214

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next214`

## Implementation

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext214()`.
- Extends the accepted generated-path rowid range and xCurrent layers with per-column xColumn cache reads for the active row.
- Records requested columns, virtual-table ordinals, rowid/_rowid_/oid alias normalization, cache-hit counts, nullable/missing projection handling, xColumn fingerprints, cost class, and next-source reprepare reasons.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext214Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next214.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext214Test.php`
  - `1 test files, 67 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next214.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next214 self-test passed`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This avoids accepted JSON table cursor/source wiring, hidden/visible constraints, generated-path rowid cost/cache/yield/xNext/xCurrent/range layers through next212, parser-level JSON table SELECT sources, and storage/VFS/B-tree/WAL surfaces. The new behavior is specifically xColumn cache consumption from the active generated-path rowid current-source cursor.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path rowid range costing, alias projection, xCurrent materialization, and current-source fingerprints.

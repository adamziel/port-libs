# PRAGMA index/table info cursor next15

## Behavior

- Added `SQLitePragmaRowCursor` for bounded `current()` / `next()` iteration over already-resolved PRAGMA rowsets.
- Added cursor entrypoints for `SQLitePragmaSchemaCatalog::executeCursor()` and `SQLiteAttachedSchemaCatalog::executeSchemaPragmaCursor()`.
- Covered `table_info`, `table_xinfo`, `index_info`, and `index_xinfo` row order, generated-column visibility, auxiliary rowid rows, EOF/rewind behavior, empty rowsets, quoted targets, and current-source schema resolution.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexTableInfoCursorTest.php`
  - `1 test files, 108 assertions, 0 failures`
  - 75 PASS lines

## Dashboard Delta

- `lane-status.json` `phpPass`: `4362 -> 4437` (+75), matching the verified focused PASS-line delta.
- `benchmarkDenominator.mapped`: unchanged; this slice adds focused native PHP behavior coverage but does not claim a newly hydrated upstream Tcl inventory unit.

## Non-Overlap

This slice does not repeat accepted PRAGMA schema current-source resolution, `index_xinfo` expression metadata, JSON table cursor behavior, SELECT SQL text dispatch, B-tree page move/freeblock/overflow clusters, WAL savepoint/rollback/checkpoint clusters, VFS file writer/lock/sync clusters, or Unicode GLOB work. It only adds a row-cursor surface over existing PRAGMA schema rowsets.

## Dependency Closure

No new support component is needed. The cursor reuses existing bounded schema catalog and attached-schema resolution primitives.

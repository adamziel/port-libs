# B-tree Vacuum Pointer-Map Freeblock Current-Source Next239

## Behavior

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext239Plan`, a final ordered drain barrier on top of the accepted next236 source-next cursor rows. It verifies that pointer-map pages and duplicate pointer-map generations are drained before payload/freeblock reuse is admitted, while preserving freeblock receipts and fenced tail pages.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext239Test.php`
- Result: `1 test files, 1338 assertions, 0 failures`
- PASS-line delta: `+138` focused PASS lines
- Expected `phpPass`: `119121 -> 119259`
- Mapped coverage: unchanged at `642 / 1589`

## Application Smoke

- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next239.php`
- Covers a `wp_options` transient delete with overflow cleanup where pointer-map page `105` is visited twice and must drain before payload/freeblock reuse.

## Non-Overlap

Extends accepted next236 source-next visibility with ordered drain admission. It does not repeat next236 visibility, next233 checkpoints, next229 resume windows, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, next236 encoding/pager/row-value/trigger/WAL slices, next235 compound/planner slices, next234 PRAGMA slices, or suite238 countability evidence.

## Dependency Closure

No new support component is needed. The patch reuses `SQLiteDatabase`, `SQLiteTableLeafPage`, `SQLitePointerMapEntry`, and accepted next236 source-next rows.

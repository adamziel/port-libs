# B-tree pointer-map freeblock vacuum current-source next135

- Behavior: adds `SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan`, a current-source composition for deleting an overflow-backed table leaf row, preserving the reusable leaf freeblock, releasing obsolete overflow pages, and applying partial incremental vacuum across the page-105 pointer-map boundary while page 106 survives as the freelist trunk.
- Application smoke: `php lanes/libsqlite/examples/application-btree-pointermap-freeblock-vacuum-current-source-next135.php --self-test`
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Test.php`
- Result: `1 test files, 176 assertions, 0 failures` with `56` PASS lines.
- Dashboard delta: `phpPass` `56681 -> 56737`; mapped coverage unchanged at `606 / 1589`.

## Non-overlap

This avoids accepted page relocation, root collapse, index-interior merge, overflow freelist release, bulk overflow freeblocks, freelist trunk pointer-map reuse, next127 all-tail pointer-map vacuum/freeblock coverage, next132 overflow freelist reuse, and next134/accepted overflow pointer-map/freeblock surfaces. The new surface is partial current-source vacuum classification across a pointer-map page boundary: released overflow page 106 remains a materialized freelist trunk with a free-page pointer-map entry while tail pages 107-110 are omitted.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP B-tree delete/freeblock materialization, pointer-map, freelist release, and incremental vacuum primitives under `lanes/libsqlite/src`.

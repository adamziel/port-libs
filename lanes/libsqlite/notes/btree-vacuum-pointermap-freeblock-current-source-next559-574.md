# B-tree Vacuum Pointer-map Freeblock Current-source Next559-574

Next559-574 extends the merged next543-558 current-source handoff proof on the same freelist current-source variant.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext559()` through `tableLeafFromDeleteResultNext574()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and current-source handoff pages 3, 106, 107, 108 after trunk receipts 2 and 105
- Focused test: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext559574Test.php`
- Example self-test: `wordpress-btree-vacuum-pointermap-freeblock-current-source-next559-574.php`
- Non-overlap: this slice only adds next559-574 current-source entrypoints and coverage after the merged next543-558 handoff; it does not repeat freelist splice construction, vacuum finalization, source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

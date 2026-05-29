# B-tree Vacuum Pointer-map Freeblock Current-source Next543-558

Next543-558 extends the merged next527-542 current-source handoff proof on the same freelist current-source variant.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext543()` through `tableLeafFromDeleteResultNext558()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and current-source handoff pages 3, 106, 107, 108 after trunk receipts 2 and 105
- Focused test: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext543558Test.php`
- Example self-test: `wordpress-btree-vacuum-pointermap-freeblock-current-source-next543-558.php`
- Non-overlap: this slice only adds next543-558 current-source entrypoints and coverage after the merged next527-542 handoff; it does not repeat freelist splice construction, vacuum finalization, source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

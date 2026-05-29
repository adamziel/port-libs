# B-tree Vacuum Pointer-map Freeblock Current-source Next447-462

Next447-462 extends the merged next431-446 freelist splice current-source proof without adding a new support component.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext447()` through `tableLeafFromDeleteResultNext462()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and current-source handoff pages 3, 106, 107, 108 after trunk receipts 2 and 105
- Focused test: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext447462Test.php`
- Example self-test: `wordpress-btree-vacuum-pointermap-freeblock-current-source-next447-462.php`
- Non-overlap: this slice only extends current-source handoff receipts after next431-446 freelist splice admission; it does not repeat next261 vacuum finalization, next259 source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

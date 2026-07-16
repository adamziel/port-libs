# B-tree Vacuum Pointer-map Freeblock Current-source Next431-446

Next431-446 extends the merged next415-430 freelist splice current-source proof without adding a new support component.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistSpliceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext431()` through `tableLeafFromDeleteResultNext446()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and reusable freelist pages 3, 106, 107, 108
- Focused test: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext431446Test.php`
- Example self-test: `application-btree-vacuum-pointermap-freeblock-current-source-next431-446.php`
- Non-overlap: this slice only extends current-source freelist splice admission after next415-430; it does not repeat next261 vacuum finalization, next259 source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

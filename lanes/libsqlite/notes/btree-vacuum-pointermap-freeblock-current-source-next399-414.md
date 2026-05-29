# B-tree Vacuum Pointer-map Freeblock Current-source Next399-414

Next399-414 extends the merged next391-398 freelist splice current-source proof without adding a new support component.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistSpliceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext399()` through `tableLeafFromDeleteResultNext414()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and reusable freelist pages 3, 106, 107, 108
- Focused test: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext399414Test.php`
- Example self-test: `wordpress-btree-vacuum-pointermap-freeblock-current-source-next399-414.php`
- Non-overlap: this slice only extends current-source freelist splice admission after next391-398; it does not repeat next261 vacuum finalization, next259 source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

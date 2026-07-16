# B-tree Vacuum Pointer-map Freeblock Current-source Next415-430

Next415-430 extends the merged next399-414 freelist splice current-source proof without adding a new support component.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistSpliceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext415()` through `tableLeafFromDeleteResultNext430()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and reusable freelist pages 3, 106, 107, 108
- Focused test: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext415430Test.php`
- Example self-test: `application-btree-vacuum-pointermap-freeblock-current-source-next415-430.php`
- Non-overlap: this slice only extends current-source freelist splice admission after next399-414; it does not repeat next261 vacuum finalization, next259 source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

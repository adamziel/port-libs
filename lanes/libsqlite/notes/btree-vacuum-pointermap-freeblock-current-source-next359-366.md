# B-tree Vacuum Pointer-map Freeblock Current-source Next359-366

Next359-366 extends the existing next263/295-358 freelist splice current-source proof without adding a new support component.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistSpliceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext359()` through `tableLeafFromDeleteResultNext366()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and reusable freelist pages 3, 106, 107, 108
- Non-overlap: this slice only extends current-source freelist splice admission; it does not repeat next261 vacuum finalization, next259 source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

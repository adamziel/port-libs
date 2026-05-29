# B-tree Vacuum Pointer-map Freeblock Current-source Next607-622

Next607-622 extends the merged next591-606 current-source handoff proof on the same freelist current-source variant.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext607()` through `tableLeafFromDeleteResultNext622()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and current-source handoff pages 3, 106, 107, 108 after trunk receipts 2 and 105
- Focused test: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext607622Test.php`
- Example self-test: `wordpress-btree-vacuum-pointermap-freeblock-current-source-next607-622.php`
- Non-overlap: this slice only adds next607-622 current-source entrypoints and coverage after the merged next591-606 handoff; it does not repeat freelist splice construction, vacuum finalization, source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

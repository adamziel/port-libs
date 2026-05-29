# B-tree Vacuum Pointer-map Freeblock Current-source Next591-606

Next591-606 extends the merged next575-590 current-source handoff proof on the same freelist current-source variant.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext591()` through `tableLeafFromDeleteResultNext606()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and current-source handoff pages 3, 106, 107, 108 after trunk receipts 2 and 105
- Focused test: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext591606Test.php`
- Example self-test: `wordpress-btree-vacuum-pointermap-freeblock-current-source-next591-606.php`
- Non-overlap: this slice only adds next591-606 current-source entrypoints and coverage after the merged next575-590 handoff; it does not repeat freelist splice construction, vacuum finalization, source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

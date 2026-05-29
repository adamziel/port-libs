# B-tree Vacuum Pointer-map Freeblock Current-source Next495-510

Next495-510 extends the merged next479-494 current-source handoff proof on the same freelist current-source variant.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext495()` through `tableLeafFromDeleteResultNext510()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and current-source handoff pages 3, 106, 107, 108 after trunk receipts 2 and 105
- Focused test: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext495510Test.php`
- Example self-test: `wordpress-btree-vacuum-pointermap-freeblock-current-source-next495-510.php`
- Non-overlap: this slice only adds next495-510 current-source entrypoints and coverage after the merged next479-494 handoff; it does not repeat freelist splice construction, vacuum finalization, source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

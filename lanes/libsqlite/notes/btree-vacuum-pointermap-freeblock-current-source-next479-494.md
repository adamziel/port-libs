# B-tree Vacuum Pointer-map Freeblock Current-source Next479-494

Next479-494 extends the merged next463-478 current-source handoff proof on the same freelist current-source variant.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext479()` through `tableLeafFromDeleteResultNext494()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and current-source handoff pages 3, 106, 107, 108 after trunk receipts 2 and 105
- Focused test: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext479494Test.php`
- Example self-test: `application-btree-vacuum-pointermap-freeblock-current-source-next479-494.php`
- Non-overlap: this slice only adds next479-494 current-source entrypoints and coverage after the accepted next463-478 handoff; it does not repeat freelist splice construction, vacuum finalization, source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

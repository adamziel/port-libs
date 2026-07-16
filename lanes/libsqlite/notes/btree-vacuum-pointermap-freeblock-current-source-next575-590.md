# B-tree Vacuum Pointer-map Freeblock Current-source Next575-590

Next575-590 extends the merged next559-574 current-source handoff proof on the same freelist current-source variant.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext575()` through `tableLeafFromDeleteResultNext590()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and current-source handoff pages 3, 106, 107, 108 after trunk receipts 2 and 105
- Focused test: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext575590Test.php`
- Example self-test: `application-btree-vacuum-pointermap-freeblock-current-source-next575-590.php`
- Non-overlap: this slice only adds next575-590 current-source entrypoints and coverage after the merged next559-574 handoff; it does not repeat freelist splice construction, vacuum finalization, source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

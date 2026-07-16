# B-tree Vacuum Pointer-map Freeblock Current-source Next623-638

Next623-638 extends the merged next607-622 current-source handoff proof on the same canonical freelist current-source variant.

- Source: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`
- Added entrypoints: `tableLeafFromDeleteResultNext623()` through `tableLeafFromDeleteResultNext638()`
- Fixture shape: auto-vacuum page image with pointer-map pages 2 and 105, deleted table leaf row 2, overflow pages 106-110, and current-source handoff pages 3, 106, 107, 108 after trunk receipts 2 and 105
- Focused test: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext623638Test.php`
- Example self-test: `application-btree-vacuum-pointermap-freeblock-current-source-next623-638.php`
- Numbered source class: not added; the established local pattern uses the canonical `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan` entrypoints over the shared freelist current-source variant
- Non-overlap: this slice only adds next623-638 current-source entrypoints and coverage after the merged next607-622 handoff; it does not repeat freelist splice construction, vacuum finalization, source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior

# B-tree Vacuum Pointer-map Freeblock Current-source Next182

- Added `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext182Plan`.
- Behavior: composes the accepted next177 replay batches into an ordered apply/truncate schedule for a copied `wp_options` transient delete. The schedule replays page 1, the table leaf/freeblock page, pointer-map page 105, and replacement overflow pages 106-108 before truncating fenced tail pages 109-110.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext182Test.php` -> `1 test files / 573 assertions / 0 failures` with 87 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next182.php` -> emits ordered replay pages `[1,3,105,106,107,108]`, pointer-map replay page `[105]`, replacement overflow pages `[106,107,108]`, truncate pages `[109,110]`, and the self-test pass line.
- Non-overlap: this is the apply/truncate ordering layer after next177 replay batches. It does not repeat next177 batch construction, next176 source-boundary checks, next173 transition auditing, next166 write admission, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, or index-interior merge.
- Dependency closure: no new support component needed; the slice reuses native page-image hashes, pointer-map dependency batches, and current-source tail fences already present in the B-tree vacuum/freeblock path.

# B-tree Vacuum Pointer-Map Freeblock Current-Source Next174

- Behavior: adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`, a bounded next-reader cursor layer over the accepted next170 current-source handoff rows. The cursor admits only post-vacuum readable pages, assigns stable batch positions and resume tokens from next page hashes/pointer-map state, and keeps vacuum-truncated source pages fenced with no resume token.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext174Test.php` -> `1 test files / 577 assertions / 0 failures` with 82 PASS lines.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next174.php --self-test` -> self-test passed.
- Non-overlap: avoids accepted next170 handoff visibility, next166 write admission, next163 source admission, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, and accepted next168/169/170 B-tree vacuum pointer-map/freeblock surfaces. This slice only adds the next-reader cursor/resume boundary over already admitted readable pages.
- Dependency closure: no new support component is needed; this reuses native PHP b-tree current-source handoff rows, page-image hashing, pointer-map entry decoding, and overflow/freeblock page images.

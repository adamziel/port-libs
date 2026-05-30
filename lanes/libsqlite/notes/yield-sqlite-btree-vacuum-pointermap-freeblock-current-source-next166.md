# B-tree vacuum pointer-map freeblock current-source next166

- Behavior: adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`, which composes the next163 current-source admission fence with final write-admission rows for the database header, compacted leaf page, pointer-map page, replacement overflow pages, and truncated current-source pages rejected after partial vacuum.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext166Test.php` -> `1 test files / 631 assertions / 0 failures` with 91 PASS lines.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next166.php --self-test` -> self-test passed.
- Non-overlap: avoids accepted next156/157/158/159/160/161/162/163 variants, next144 pointer-map vacuum rows, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, index-interior merge, and freelist trunk pointer-map reuse. This slice is specifically the final write-admission and stale-current-source exclusion guard after the current-source admission fence.
- Dependency closure: no new support component is needed; this reuses native PHP b-tree vacuum page images, compacted leaf-page writes, overflow chain encoding, freelist allocation, and auto-vacuum pointer-map write primitives.

# B-tree vacuum pointer-map freeblock current-source next163

- Behavior: adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`, which fences a current-source table leaf delete/vacuum flow by tying the deleted leaf freeblock, released overflow source chain, surviving free pages, replacement overflow chain, and final pointer-map parent links into one admission summary.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext163Test.php` -> `1 test files / 402 assertions / 0 failures` with 78 PASS lines.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next163.php` -> self-test passed.
- Non-overlap: avoids accepted next160 chain pointer validation, next159 row imaging, next156 replacement allocation, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, and freelist trunk pointer-map reuse. This slice adds current-source admission fencing that rejects truncated source pages while admitting only surviving pages reused by the replacement overflow chain.
- Dependency closure: no new support component is needed; this reuses native PHP b-tree delete/vacuum, freelist allocation, overflow encoding, page image application, and auto-vacuum pointer-map primitives.

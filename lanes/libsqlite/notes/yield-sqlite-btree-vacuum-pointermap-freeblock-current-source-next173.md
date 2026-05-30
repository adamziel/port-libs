# B-tree Vacuum Pointer-Map Freeblock Current Source Next173

- Behavior: adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext173Plan`, an additive current-source transition audit over the accepted next167 delete/vacuum/rewrite path. It records stable leaf-freeblock pages, replacement overflow pages, rewritten current-source overflow next-pointers, and truncated tail pages so stale source bytes cannot be admitted after partial vacuum.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext173Test.php` -> `1 test files / 369 assertions / 0 failures` with 75 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next173.php --self-test` -> self-test passed.
- Dependency closure: no new support component needed; reuses native b-tree leaf parsing, overflow-chain materialization, incremental-vacuum truncation, and auto-vacuum pointer-map helpers.
- Non-overlap: this is a transition/admission audit only. It does not repeat next167 final leaf audit, next166 write admission, next164 chain continuity, accepted overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization.

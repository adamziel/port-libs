# B-tree Vacuum Pointer-Map Freeblock Current Source Next240

- Slice: `btree-vacuum-pointermap-freeblock-current-source-next240`
- Base accepted HEAD: `77f1eca632d8678462117dec9914e48df8b2921f`
- Behavior: adds reuse-admission validation on top of the accepted next236 source-next cursor. Payload/freeblock pages become reusable only after a visible pointer-map generation, duplicate pointer-map page reads stay current, source-next links remain chained, and tail pages stay fenced.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext240Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next240.php`
- Expected dashboard movement: `phpPass` +131 focused PASS lines (`119121 -> 119252`), `phpFail` remains `0`, mapped coverage unchanged.
- Non-overlap: builds after next236 and does not repeat next236 cursor rows, next233 checkpoints, next229 resume windows, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization.
- Dependency closure: no new support component needed; the slice reuses existing B-tree page, pointer-map, record, and current-source plan helpers.

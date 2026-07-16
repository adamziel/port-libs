# B-tree vacuum pointer-map freeblock current-source next262

- Behavior: adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext262Plan`, a current-source replay barrier over the next258 handoff rows. It keeps pointer-map replay barriers ahead of reusable freeblock consumption, carries stale-slot fences forward, preserves leaf receipts, and chains replay tokens before the next-source writer consumes pages.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext262Test.php` -> `1 test files, 1400 assertions, 0 failures` with 152 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next262.php --self-test`.
- Non-overlap: avoids accepted batch221 next258 stale-slot fencing, next254 write slots, next249 allocation publication, next245 cursor admission, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, PRAGMA, encoding, and suite-runner surfaces. This slice only records the final replay-barrier ordering before next-source freeblock consumption.
- Dependency closure: no new support component needed; next262 reuses existing native PHP B-tree page image, pointer-map, freeblock, and next258 handoff primitives.

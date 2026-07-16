# B-tree vacuum pointer-map freeblock current-source next200

- Behavior: adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext200Plan`, an additive current-source commit boundary over next194 writer admission. The plan commits only writer-admitted leaf freeblock and overflow-freelist pages to the next current source while keeping truncated tail pages fenced out.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext200Test.php`.
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next200.php`.
- Non-overlap: avoids accepted next194 writer admission, next190 reader leases, next187 publish barriers, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, index-interior merge, and freelist trunk pointer-map reuse. This slice only adds the post-writer current-source commit classification.
- Dependency closure: no new support component is needed; the slice reuses native b-tree delete/freeblock, overflow freelist, incremental vacuum, and auto-vacuum pointer-map primitives already present in the libsqlite lane.

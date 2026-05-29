# btree vacuum pointermap freeblock current-source next343-350

- Extends the accepted freelist splice surface through next343, next344, next345, next346, next347, next348, next349, and next350.
- Keeps the current-source B-tree vacuum pointer-map/freeblock rows on the shared `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistSpliceVariant` implementation.
- Validates pointer-map trunk anchors are seen before leaf slots, leaf slots stay ordered, write offsets match vacuum finalization, and fenced tail pages 109/110 remain rejected from freelist reuse.
- WordPress smoke: `examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next343-350.php`.
- Dependency closure: no new support component needed; next343-350 reuse next261 vacuum finalization rows and the accepted next335-342 freelist splice path.
- Non-overlap: adds only the follow-on next343-350 slice gates, factories, test coverage, and smoke example; it does not repeat next335-342 artifacts, next261 reusable-slot finalization, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior.

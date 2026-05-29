# btree vacuum pointermap freeblock current-source next335-342

- Extends the accepted freelist splice surface through next335, next336, next337, next338, next339, next340, next341, and next342.
- Keeps the current-source B-tree vacuum pointer-map/freeblock rows on the shared `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistSpliceVariant` implementation.
- Validates pointer-map trunk anchors are seen before leaf slots, leaf slots stay ordered, write offsets match vacuum finalization, and fenced tail pages 109/110 remain rejected from freelist reuse.
- WordPress smoke: `examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next335-342.php`.
- Dependency closure: no new support component needed; next335-342 reuse next261 vacuum finalization rows and the accepted next331-334 freelist splice path.
- Non-overlap: adds only the follow-on next335-342 slice gates, factories, test coverage, and smoke example; it does not repeat next331-334 artifacts, next261 reusable-slot finalization, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior.

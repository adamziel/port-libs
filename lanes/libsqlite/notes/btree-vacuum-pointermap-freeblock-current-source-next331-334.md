# btree vacuum pointermap freeblock current-source next331-334

- Extends the accepted freelist splice surface through next331, next332, next333, and next334.
- Keeps the current-source B-tree vacuum pointer-map/freeblock rows on the shared `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistSpliceVariant` implementation.
- Validates pointer-map trunk anchors are seen before leaf slots, leaf slots stay ordered, write offsets match vacuum finalization, and fenced tail pages 109/110 remain rejected from freelist reuse.
- WordPress smoke: `examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next331-334.php`.
- Dependency closure: no new support component needed; next331-334 reuse next261 vacuum finalization rows and the accepted next327-330 freelist splice path.
- Non-overlap: adds only the follow-on next331-334 slice gates, factories, test coverage, and smoke example; it does not repeat next327-330 artifacts, next261 reusable-slot finalization, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior.

# btree vacuum pointermap freeblock current-source next351-358

- Extends the accepted freelist splice surface through next351, next352, next353, next354, next355, next356, next357, and next358.
- Keeps the current-source B-tree vacuum pointer-map/freeblock rows on the shared `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistSpliceVariant` implementation.
- Validates pointer-map trunk anchors are seen before leaf slots, leaf slots stay ordered, write offsets match vacuum finalization, and fenced tail pages 109/110 remain rejected from freelist reuse.
- Application smoke: `examples/application-btree-vacuum-pointermap-freeblock-current-source-next351-358.php`.
- Dependency closure: no new support component needed; next351-358 reuse next261 vacuum finalization rows and the accepted next343-350 freelist splice path.
- Non-overlap: adds only the follow-on next351-358 slice gates, factories, test coverage, and smoke example; it does not repeat next343-350 artifacts, next261 reusable-slot finalization, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior.

# real-upstream-corpus-btree-index-dynamic-20260531T072835Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index4.test`.
- Ported sections: `index4-1.1` through `index4-2.2`.
- Focused PHP coverage: `SQLiteRealUpstreamBtreeIndex4LargeBuildDynamicTest.php` adds 1,203 TestRunner cases around large `CREATE INDEX` builds, limited-memory index builds, mixed text/NULL/overflow-payload index builds, empty/single-row index roots, and UNIQUE duplicate rejection.
- Non-overlap: avoids accepted `index.test` lifecycle, `index2` wide-column, `index5` write-order, page relocation, root collapse, overflow freelist, VFS writer/sync/lock, and rollback clusters.
- Dependency closure: no new support component needed; the slice reuses the lane-local B-tree/index dynamic corpus planner and index build/error modeling.

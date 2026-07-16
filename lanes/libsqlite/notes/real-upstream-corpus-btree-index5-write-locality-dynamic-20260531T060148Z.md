# real-upstream-corpus-btree-index-dynamic-20260531T060148Z-0

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index5.test`.
- Ported sections: `index5-1.1` through `index5-1.3`.
- Behavior: `CREATE INDEX i1 ON t1(x)` over a 100000-row, 1024-byte page database preserves page size and produces mostly forward database page writes under the upstream VFS `xWrite` audit invariant.
- Focused PHP coverage: `SQLiteRealUpstreamBtreeIndex5WriteLocalityDynamicTest.php` adds 1200 dynamic upstream-derived cases plus count, invalid-count, and dependency-closure checks.
- Non-overlap: avoids accepted B-tree page relocation, root collapse, overflow freelist/freeblock release, partial-index theorem/stat rows, indexed-DML, whereJ range-cost, VFS writer/sync/lock, and rollback/WAL clusters. This slice owns `index5.test` create-index write locality only.
- Dependency closure: no new support component needed; this reuses the lane-local B-tree/index dynamic corpus planner and bounded VFS write-locality counters.

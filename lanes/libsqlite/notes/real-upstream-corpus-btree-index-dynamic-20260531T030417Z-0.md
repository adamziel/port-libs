# real-upstream-corpus-btree-index-dynamic-20260531T030417Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index6.test`.
- Owned upstream range: `index6-1.1` through `index6-6.2`.
- Added focused PHP corpus: `SQLiteRealUpstreamBtreeIndex6EarlyPartialDynamicTest.php`.
- Focused assertion/PASS target: 1200 dynamic TestRunner cases plus source-range, invalid-size, and dependency-closure checks.
- Non-overlap: this covers early partial-index parser/stat/unique/VACUUM/name-qualifier/update-replace behavior and does not repeat accepted index6 late theorem/regression, index4 create-index stress, index5 write-locality, indexA partial-affinity, bestindex, root-collapse, page-move, overflow-freelist, or VFS/WAL clusters.
- Dependency closure: no new support component needed; reuses lane-local B-tree/index dynamic corpus helpers.

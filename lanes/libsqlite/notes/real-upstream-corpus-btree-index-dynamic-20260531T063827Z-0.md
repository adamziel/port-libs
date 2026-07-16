# real-upstream-corpus-btree-index-dynamic-20260531T063827Z-0

Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index5.test`
sections `index5-1.1` through `index5-1.3`.

This slice adds `SQLiteRealUpstreamCorpusBtreeIndexDynamicIndex5BuildWriteTest`
for the upstream CREATE INDEX write-locality guard: a 1024-byte-page,
100000-row table builds/drops/rebuilds index `i1`, then the VFS xWrite page
trace must be predominantly forward. It reuses the existing lane-local
`SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialIndexBuildWriteCases()`
corpus and adds 1203 distinct focused TestRunner cases.

Non-overlap: this covers the upstream `index5.test` CREATE INDEX xWrite
page-order guard and avoids accepted table/index page relocation, root
collapse, overflow freelist release, VFS writer, sync, lock, rollback-commit,
and prior index5 transition tests.

Dependency closure: no new support component is needed; the existing bounded
B-tree/index dynamic corpus planner is reused.

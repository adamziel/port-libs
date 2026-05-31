# real-upstream-corpus-btree-index-dynamic-20260531T033159Z-0

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index4.test`.
- Ported scenarios: `index4-1.1` through `index4-2.2`, covering bulk `CREATE INDEX`, limited-memory repeat index build, mixed large payload index build, single-row and empty-table index builds, and duplicate failure for `CREATE UNIQUE INDEX`.
- Lane files: `SQLiteBTreeIndexDynamicCorpusIndex4Test.php` and the aggregate dynamic corpus source/count assertions in `SQLiteBTreeIndexDynamicCorpusPlanTest.php`.
- Focused movement: `SQLiteBTreeIndexDynamicCorpusIndex4Test.php` adds `1203` focused TestRunner PASS cases and `19407` assertions. Combined focused B-tree dynamic corpus verification passed at `2 test files / 84574 assertions / 0 failures`.
- Non-overlap: uses upstream `index4.test` create-index stress behavior; it does not repeat accepted B-tree page relocation, root collapse, overflow freelist release, freeblock duplicate, whereK OR factoring, or bestindex surfaces.
- Dependency closure: no new support component needed; this reuses the existing lane-local B-tree/index dynamic corpus planner and create-index stress case generator.

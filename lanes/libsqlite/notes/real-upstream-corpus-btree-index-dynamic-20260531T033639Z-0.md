# Real upstream corpus: B-tree/index dynamic bestindex1

Slice: `real-upstream-corpus-btree-index-dynamic-20260531T033639Z-0`

- Upstream source truth:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindex1.test`
- Upstream sections covered:
  `bestindex1-1.1`, `bestindex1-1.2`, `bestindex1-2.2.use.4`,
  `bestindex1-2.2.omit.4`, `bestindex1-2.2.use2.5`, `bestindex1-3.4`,
  `bestindex1-3.5`, `bestindex1-4.1`, and `bestindex1-5.0`.
- Focused addition:
  `SQLiteRealUpstreamBestIndex1VirtualTableDynamicTest.php` adds 1000
  distinct TestRunner PASS cases plus source-range, invalid-size, and
  dependency-closure guards over
  `SQLiteBTreeIndexDynamicCorpusPlan::bestindex1VirtualTableInConstraintCases(1000)`.
- Behavior covered:
  virtual-table usable equality constraints, `IN (...)` represented as equality
  for xBestIndex, second xBestIndex callback with the IN equality marked
  unusable, omitted versus residual filtering, outer-loop IN register
  stability across two virtual tables, temporary B-tree ORDER BY retention, and
  wrong-argument virtual-table module errors.
- Non-overlap:
  This owns `bestindex1.test` and does not repeat accepted `bestindex2` through
  `bestindexF`, `autoindex1` through `autoindex5`, `index3`, `index5`,
  `index6`, `index7`, `index8`, `index9`, `indexA`, B-tree page relocation,
  overflow freelist/freeblock, JSON, WAL, VFS, PRAGMA, SELECT, or
  source-neutral cleanup clusters.
- Dependency closure:
  No new support component is needed; this reuses the existing lane-local
  B-tree/index dynamic corpus planner and virtual-table xBestIndex
  IN/equality metadata modelling.
